<?php

namespace App\Console\Commands;

use App\Services\MqttDeviceStatusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * Écoute les topics de statut MQTT et met à jour Device.status / last_seen_at.
 *
 * Matching : status_topic exact, {mqtt_topic}/status, slug Tasmota tele|stat|cmnd/{slug}/…
 * Payloads : JSON, LWT Online/Offline, POWER ON/OFF.
 */
class MqttStatusListenCommand extends Command
{
    protected $signature = 'mqtt:listen-status
        {--topic=# : Topic MQTT à écouter (wildcards +/# supportés)}';

    protected $description = 'S\'abonne aux topics de statut MQTT et met à jour les devices';

    public function handle(MqttDeviceStatusService $statuses): int
    {
        $topic = (string) $this->option('topic');
        $this->info("Écoute MQTT sur [{$topic}]… (Ctrl+C pour quitter)");

        try {
            $mqtt = MQTT::connection();

            $mqtt->subscribe($topic, function (string $topic, string $message) use ($statuses): void {
                $preview = strlen($message) > 200 ? substr($message, 0, 200).'…' : $message;
                $this->line("[{$topic}] {$preview}");

                $device = $statuses->handle($topic, $message);

                if ($device !== null) {
                    $label = $device->connectivityLabel();
                    $this->info("Device #{$device->id} mis à jour ({$label}).");
                }
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
