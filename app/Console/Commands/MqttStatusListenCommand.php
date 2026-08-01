<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Écoute les topics de statut remontés par les Raspberry Pi
 * et met à jour Device.status / last_seen_at.
 *
 * Convention attendue : display/led/+/status (payload JSON libre).
 */
class MqttStatusListenCommand extends Command
{
    protected $signature = 'mqtt:listen-status
        {--topic=display/led/+/status : Topic MQTT à écouter (wildcards +/# supportés)}';

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
                    /** @var array<string, mixed>|null $status */
                    $status = json_decode($message, true, 512, JSON_THROW_ON_ERROR);
                } catch (Throwable) {
                    Log::warning('Statut MQTT non-JSON ignoré', compact('topic', 'message'));

                    return;
                }

                // topic ex: display/led/salon/status → base = display/led/salon
                // ou display/led/status → base = display/led
                $baseTopic = preg_replace('#/status$#', '', $topic) ?? $topic;

                $device = Device::query()
                    ->where('mqtt_topic', $baseTopic)
                    ->orWhere('mqtt_topic', $topic)
                    ->first();

                if ($device === null) {
                    $device = Device::query()->get()->first(
                        fn (Device $candidate): bool => str_starts_with($baseTopic, $candidate->mqtt_topic)
                    );
                }

                if ($device === null) {
                    Log::notice('Aucun device pour topic statut', ['topic' => $topic]);

                    return;
                }

                $device->forceFill([
                    'status' => $status,
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
