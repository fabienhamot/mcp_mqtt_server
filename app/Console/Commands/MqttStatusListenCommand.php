<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Écoute les topics de statut MQTT et met à jour Device.status / last_seen_at.
 *
 * Matching : status_topic exact, sinon mqtt_topic + /status, sinon préfixe mqtt_topic.
 */
class MqttStatusListenCommand extends Command
{
    protected $signature = 'mqtt:listen-status
        {--topic=# : Topic MQTT à écouter (wildcards +/# supportés)}';

    protected $description = 'S\'abonne aux topics de statut MQTT et met à jour les devices';

    public function handle(): int
    {
        $topic = (string) $this->option('topic');
        $this->info("Écoute MQTT sur [{$topic}]… (Ctrl+C pour quitter)");

        try {
            $mqtt = MQTT::connection();

            $mqtt->subscribe($topic, function (string $topic, string $message): void {
                $this->line("[{$topic}] {$message}");

                try {
                    /** @var array<string, mixed> $status */
                    $status = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    Log::warning('Statut MQTT non-JSON ignoré', compact('topic', 'message'));

                    return;
                }

                $device = Device::query()->where('status_topic', $topic)->first();

                if ($device === null && str_ends_with($topic, '/status')) {
                    $baseTopic = preg_replace('#/status$#', '', $topic) ?? $topic;
                    $device = Device::query()
                        ->where('mqtt_topic', $baseTopic)
                        ->first();
                }

                if ($device === null) {
                    $device = Device::query()->get()->first(
                        fn (Device $candidate): bool => $candidate->resolvedStatusTopic() === $topic
                    );
                }

                if ($device === null) {
                    Log::notice('Aucun device pour topic statut', ['topic' => $topic]);

                    return;
                }

                $previous = $device->status ?? [];
                $preserved = array_intersect_key($previous, array_flip(['last_command', 'last_command_at']));

                $device->forceFill([
                    'status' => array_merge($preserved, $status),
                    'last_seen_at' => now(),
                ])->save();

                $this->info("Device #{$device->id} mis à jour.");
            }, 0);

            $mqtt->loop(true);
        } catch (Throwable $e) {
            $this->error('Erreur MQTT : '.$e->getMessage());
            Log::error('mqtt:listen-status failed', ['error' => $e->getMessage()]);

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
