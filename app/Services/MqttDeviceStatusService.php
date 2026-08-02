<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * Associe un message MQTT (JSON ou texte Tasmota) à un Device et met à jour status / last_seen_at.
 *
 * Topics reconnus notamment :
 * - status_topic exact / {mqtt_topic}/status
 * - tele/{topic}/STATE|SENSOR|LWT
 * - stat/{topic}/RESULT|POWER|…
 */
class MqttDeviceStatusService
{
    /**
     * @return Device|null Device mis à jour, ou null si ignoré / inconnu
     */
    public function handle(string $topic, string $message): ?Device
    {
        $device = $this->findDevice($topic);

        if ($device === null) {
            Log::notice('Aucun device pour topic statut', ['topic' => $topic]);

            return null;
        }

        $leaf = $this->topicLeaf($topic);
        $trimmed = trim($message);

        if ($this->isLwtTopic($leaf)) {
            return $this->applyLwt($device, $trimmed);
        }

        if ($trimmed === '') {
            return null;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($trimmed, true, 512, JSON_THROW_ON_ERROR);

            return $this->applyJsonStatus($device, $decoded, $leaf);
        } catch (\Throwable) {
            return $this->applyPlainStatus($device, $trimmed, $leaf);
        }
    }

    public function findDevice(string $topic): ?Device
    {
        $device = Device::query()->where('status_topic', $topic)->first();

        if ($device !== null) {
            return $device;
        }

        if (str_ends_with($topic, '/status')) {
            $baseTopic = preg_replace('#/status$#', '', $topic) ?? $topic;
            $device = Device::query()->where('mqtt_topic', $baseTopic)->first();
            if ($device !== null) {
                return $device;
            }
        }

        $slug = $this->extractDeviceSlug($topic);

        return Device::query()->get()->first(
            function (Device $candidate) use ($topic, $slug): bool {
                if ($candidate->resolvedStatusTopic() === $topic) {
                    return true;
                }

                if ($slug === null) {
                    return false;
                }

                return $this->deviceMatchesSlug($candidate, $slug);
            }
        );
    }

    /**
     * Identifiant Tasmota / device dans tele|stat|cmnd/{slug}/…
     */
    public function extractDeviceSlug(string $topic): ?string
    {
        if (preg_match('#^(?:tele|stat|cmnd)/([^/]+)/#i', $topic, $m) === 1) {
            return $m[1];
        }

        return null;
    }

    public function deviceMatchesSlug(Device $device, string $slug): bool
    {
        $haystacks = array_filter([
            (string) $device->mqtt_topic,
            (string) ($device->status_topic ?? ''),
            $device->resolvedStatusTopic(),
        ]);

        foreach ($haystacks as $haystack) {
            if ($haystack === $slug) {
                return true;
            }

            if (str_contains($haystack, '/'.$slug.'/') || str_ends_with($haystack, '/'.$slug)) {
                return true;
            }

            // mqtt_topic = cmnd/tasmota_xxx/POWER
            if (preg_match('#(?:^|/)'.preg_quote($slug, '#').'(?:/|$)#', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyJsonStatus(Device $device, array $status, string $leaf): Device
    {
        $previous = $device->status ?? [];
        $preserved = array_intersect_key($previous, array_flip(['last_command', 'last_command_at', 'lwt']));

        $merged = array_merge($preserved, $status, [
            'source_topic_leaf' => $leaf,
        ]);

        // STATE / SENSOR Tasmota → considérer en ligne
        $merged['lwt'] = 'Online';
        $merged['online'] = true;

        $device->forceFill([
            'status' => $merged,
            'last_seen_at' => now(),
        ])->save();

        return $device->refresh();
    }

    private function applyLwt(Device $device, string $payload): Device
    {
        $normalized = strtolower($payload);
        $online = in_array($normalized, ['online', '1', 'true'], true);

        $previous = $device->status ?? [];
        $preserved = array_intersect_key($previous, array_flip(['last_command', 'last_command_at']));

        $status = array_merge($preserved, [
            'lwt' => $online ? 'Online' : 'Offline',
            'online' => $online,
        ]);

        $device->forceFill([
            'status' => $status,
            // Offline : remonter last_seen au-delà du seuil pour isOnline()
            'last_seen_at' => $online
                ? now()
                : now()->subSeconds(Device::ONLINE_THRESHOLD_SECONDS + 1),
        ])->save();

        return $device->refresh();
    }

    private function applyPlainStatus(Device $device, string $payload, string $leaf): ?Device
    {
        // POWER / RESULT texte (ON, OFF, TOGGLE…)
        if (! in_array(strtoupper($leaf), ['POWER', 'POWER1', 'POWER2', 'POWER3', 'POWER4', 'RESULT'], true)
            && ! preg_match('/^(ON|OFF|TOGGLE)$/i', $payload)) {
            Log::warning('Statut MQTT non-JSON ignoré', [
                'topic_leaf' => $leaf,
                'message' => $payload,
            ]);

            return null;
        }

        $previous = $device->status ?? [];
        $preserved = array_intersect_key($previous, array_flip(['last_command', 'last_command_at', 'lwt']));

        $device->forceFill([
            'status' => array_merge($preserved, [
                'power' => strtoupper($payload),
                'source_topic_leaf' => $leaf,
                'lwt' => 'Online',
                'online' => true,
            ]),
            'last_seen_at' => now(),
        ])->save();

        return $device->refresh();
    }

    private function isLwtTopic(string $leaf): bool
    {
        return strcasecmp($leaf, 'LWT') === 0;
    }

    private function topicLeaf(string $topic): string
    {
        $parts = explode('/', $topic);

        return (string) end($parts);
    }
}
