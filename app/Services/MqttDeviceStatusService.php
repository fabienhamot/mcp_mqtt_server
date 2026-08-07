<?php

namespace App\Services;

use App\Models\Device;
use Illuminate\Support\Facades\Log;

/**
 * Associe un message MQTT à un Device et met à jour status / last_seen_at.
 *
 * Matching :
 * - capabilities.status_items[].topic (éléments custom)
 * - status_topic exact / {mqtt_topic}/status
 * - tele|stat|cmnd/{slug}/… (Tasmota)
 * - leaf LWT / online (présence)
 */
class MqttDeviceStatusService
{
    /**
     * @return Device|null Device mis à jour, ou null si ignoré / inconnu
     */
    public function handle(string $topic, string $message): ?Device
    {
        $trimmed = trim($message);
        $leaf = $this->topicLeaf($topic);

        $matchedItem = null;
        $device = $this->findDevice($topic, $matchedItem);

        if ($device === null) {
            Log::notice('Aucun device pour topic statut', ['topic' => $topic]);

            return null;
        }

        if ($matchedItem !== null) {
            return $this->applyStatusItem($device, $matchedItem, $trimmed, $leaf);
        }

        if ($this->isPresenceLeaf($leaf)) {
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

    /**
     * @param  array{key: string, label: string, topic: string, path: ?string, map: array<string, string>}|null  $matchedItem
     */
    public function findDevice(string $topic, ?array &$matchedItem = null): ?Device
    {
        $matchedItem = null;

        foreach (Device::query()->get() as $candidate) {
            foreach ($candidate->resolvedStatusItems() as $item) {
                if ($item['topic'] === $topic) {
                    $matchedItem = $item;

                    return $candidate;
                }
            }
        }

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

            if (preg_match('#(?:^|/)'.preg_quote($slug, '#').'(?:/|$)#', $haystack) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array{key: string, label: string, topic: string, path: ?string, map: array<string, string>}  $item
     */
    private function applyStatusItem(Device $device, array $item, string $payload, string $leaf): Device
    {
        $raw = $this->decodePayloadValue($payload);

        if ($item['path'] !== null && is_array($raw)) {
            $raw = data_get($raw, $item['path']);
        }

        $value = $this->mapStatusValue($raw, $item['map']);

        $previous = $device->status ?? [];
        $preserved = array_intersect_key(
            $previous,
            array_flip(['last_command', 'last_command_at', 'lwt', 'online'])
        );

        // Conserver les autres status_items déjà connus
        foreach ($device->resolvedStatusItems() as $other) {
            $k = $other['key'];
            if ($k !== $item['key'] && array_key_exists($k, $previous)) {
                $preserved[$k] = $previous[$k];
            }
        }

        $status = array_merge($preserved, [
            $item['key'] => $value,
        ]);

        if ($this->isPresenceLeaf($leaf) || $item['key'] === 'online') {
            $online = $this->coerceOnline($value);
            $status['lwt'] = $online ? 'Online' : 'Offline';
            $status['online'] = $online;
            $lastSeen = $online
                ? now()
                : now()->subSeconds(Device::ONLINE_THRESHOLD_SECONDS + 1);
        } else {
            $status['lwt'] = 'Online';
            $status['online'] = true;
            $lastSeen = now();
        }

        $device->forceFill([
            'status' => $status,
            'last_seen_at' => $lastSeen,
        ])->save();

        return $device->refresh();
    }

    /**
     * @param  array<string, mixed>  $status
     */
    private function applyJsonStatus(Device $device, array $status, string $leaf): Device
    {
        $previous = $device->status ?? [];
        $itemKeys = array_column($device->resolvedStatusItems(), 'key');
        $preserveKeys = array_merge(['last_command', 'last_command_at', 'lwt', 'online'], $itemKeys);
        $preserved = array_intersect_key($previous, array_flip($preserveKeys));

        $merged = array_merge($preserved, $status, [
            'source_topic_leaf' => $leaf,
            'lwt' => 'Online',
            'online' => true,
        ]);

        $device->forceFill([
            'status' => $merged,
            'last_seen_at' => now(),
        ])->save();

        return $device->refresh();
    }

    private function applyLwt(Device $device, string $payload): Device
    {
        $online = $this->coerceOnline($payload);

        $previous = $device->status ?? [];
        $itemKeys = array_column($device->resolvedStatusItems(), 'key');
        $preserveKeys = array_merge(['last_command', 'last_command_at'], $itemKeys);
        $preserved = array_intersect_key($previous, array_flip($preserveKeys));

        $status = array_merge($preserved, [
            'lwt' => $online ? 'Online' : 'Offline',
            'online' => $online,
        ]);

        $device->forceFill([
            'status' => $status,
            'last_seen_at' => $online
                ? now()
                : now()->subSeconds(Device::ONLINE_THRESHOLD_SECONDS + 1),
        ])->save();

        return $device->refresh();
    }

    private function applyPlainStatus(Device $device, string $payload, string $leaf): ?Device
    {
        if ($this->isPresenceLeaf($leaf)) {
            return $this->applyLwt($device, $payload);
        }

        if (! in_array(strtoupper($leaf), ['POWER', 'POWER1', 'POWER2', 'POWER3', 'POWER4', 'RESULT'], true)
            && ! preg_match('/^(ON|OFF|TOGGLE|true|false|1|0)$/i', $payload)) {
            Log::warning('Statut MQTT non-JSON ignoré', [
                'topic_leaf' => $leaf,
                'message' => $payload,
            ]);

            return null;
        }

        $previous = $device->status ?? [];
        $itemKeys = array_column($device->resolvedStatusItems(), 'key');
        $preserveKeys = array_merge(['last_command', 'last_command_at', 'lwt'], $itemKeys);
        $preserved = array_intersect_key($previous, array_flip($preserveKeys));

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

    private function decodePayloadValue(string $payload): mixed
    {
        if ($payload === '') {
            return null;
        }

        try {
            return json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return $payload;
        }
    }

    /**
     * @param  array<string, string>  $map
     */
    private function mapStatusValue(mixed $raw, array $map): mixed
    {
        if ($raw === null) {
            return null;
        }

        if (is_bool($raw)) {
            $lookup = $raw ? 'true' : 'false';
        } else {
            $lookup = is_scalar($raw) ? (string) $raw : null;
        }

        if ($lookup !== null && $map !== [] && array_key_exists($lookup, $map)) {
            return $map[$lookup];
        }

        return $raw;
    }

    private function coerceOnline(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['online', '1', 'true', 'on'], true);
    }

    private function isPresenceLeaf(string $leaf): bool
    {
        return strcasecmp($leaf, 'LWT') === 0 || strcasecmp($leaf, 'online') === 0;
    }

    private function topicLeaf(string $topic): string
    {
        $parts = explode('/', $topic);

        return (string) end($parts);
    }
}
