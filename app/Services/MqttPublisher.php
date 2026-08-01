<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

class MqttPublisher
{
    /**
     * Publie un payload JSON validé sur le topic MQTT du device.
     *
     * @throws Throwable
     */
    public function publish(string $topic, DisplayPayload $payload, bool $retain = false): void
    {
        $message = $payload->toJson();

        Log::info('MQTT publish', [
            'topic' => $topic,
            'payload' => $payload->toArray(),
        ]);

        try {
            MQTT::publish($topic, $message, $retain);
        } catch (Throwable $e) {
            Log::error('MQTT publish failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Publie un message brut (ex. pour tests / statut).
     */
    public function publishRaw(string $topic, string $message, bool $retain = false): void
    {
        MQTT::publish($topic, $message, $retain);
    }
}
