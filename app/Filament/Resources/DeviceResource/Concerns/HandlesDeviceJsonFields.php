<?php

namespace App\Filament\Resources\DeviceResource\Concerns;

use App\Support\DeviceCapabilityCatalog;
use Illuminate\Validation\ValidationException;

trait HandlesDeviceJsonFields
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function fillJsonFields(array $data): array
    {
        $capabilities = $data['capabilities'] ?? null;
        if (! is_array($capabilities)) {
            $capabilities = DeviceCapabilityCatalog::normalize(
                null,
                (string) ($data['type'] ?? 'led_display')
            );
        } else {
            $capabilities = DeviceCapabilityCatalog::normalize(
                $capabilities,
                (string) ($data['type'] ?? 'generic')
            );
        }

        $data['status_items'] = $capabilities['status_items'];

        $data['capabilities_json'] = json_encode(
            ['commands' => $capabilities['commands']],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $status = $data['status'] ?? null;
        $data['status_json'] = json_encode(
            $status ?? new \stdClass,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        unset($data['capabilities'], $data['status']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractJsonFields(array $data): array
    {
        $capabilities = $this->decodeJsonObject(
            $data['capabilities_json'] ?? null,
            'Capabilities'
        );

        $capabilities['status_items'] = DeviceCapabilityCatalog::normalizeStatusItems(
            $data['status_items'] ?? []
        );

        $data['capabilities'] = $capabilities;
        $data['status'] = $this->decodeJsonObject(
            $data['status_json'] ?? null,
            'Statut',
            allowNull: true
        );

        unset($data['capabilities_json'], $data['status_json'], $data['status_items']);

        return $data;
    }

    protected function decodeJsonObject(mixed $raw, string $label, bool $allowNull = false): ?array
    {
        if ($raw === null || $raw === '') {
            return $allowNull ? null : [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        if (! is_string($raw)) {
            throw ValidationException::withMessages([
                'capabilities_json' => "{$label} JSON invalide.",
            ]);
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                str_contains(strtolower($label), 'capab') ? 'capabilities_json' : 'status_json' => "{$label} JSON invalide.",
            ]);
        }

        return $decoded;
    }
}
