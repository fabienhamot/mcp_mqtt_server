<?php

namespace App\Services;

use App\Models\Device;
use App\Models\DisplayLog;
use App\Models\User;
use App\Support\DeviceCapabilityCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class DeviceCommandService
{
    public function __construct(
        private readonly MqttPublisher $mqtt,
        private readonly DevicePermissionService $permissions,
    ) {}

    /**
     * Invoque une commande déclarée dans device.capabilities.
     *
     * @param  array<string, mixed>  $params
     * @return array{device: Device, command: string, topic: string, payload: array<string, mixed>|string, log_id: int}
     *
     * @throws RuntimeException|InvalidArgumentException|Throwable
     */
    public function invoke(User $user, int $deviceId, string $command, array $params = []): array
    {
        $command = trim($command);

        if ($command === '') {
            throw new InvalidArgumentException('Le nom de commande est requis.');
        }

        $device = $this->permissions->authorizeCommand($user, $deviceId, $command);
        $capabilities = $device->resolvedCapabilities();
        $definition = $capabilities['commands'][$command] ?? null;

        if (! is_array($definition)) {
            throw new RuntimeException(
                "Commande « {$command} » inconnue sur le device #{$device->id}. ".
                'Commandes : '.implode(', ', DeviceCapabilityCatalog::commandNames($capabilities) ?: ['(aucune)'])
            );
        }

        $validated = $this->validateParams($definition['params'] ?? [], $params);
        $payload = $this->buildPayload($definition['payload'] ?? [], $validated);
        $topic = $this->resolveTopic($device, $definition);
        $retain = (bool) ($definition['retain'] ?? false);

        if (blank($topic)) {
            throw new RuntimeException("Le device #{$device->id} n'a pas de topic MQTT pour cette commande.");
        }

        $message = is_string($payload)
            ? $payload
            : json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return DB::transaction(function () use ($user, $device, $command, $topic, $payload, $message, $retain) {
            $this->mqtt->publishRaw($topic, $message, $retain);

            $logPayload = is_array($payload) ? $payload : ['raw' => $payload];
            $logPayload['_command'] = $command;

            $log = DisplayLog::query()->create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'payload' => $logPayload,
            ]);

            $device->forceFill([
                'status' => array_merge($device->status ?? [], [
                    'last_command' => $logPayload,
                    'last_command_at' => now()->toIso8601String(),
                ]),
            ])->save();

            Log::info('Device command invoked', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'command' => $command,
                'topic' => $topic,
                'payload' => $logPayload,
                'log_id' => $log->id,
            ]);

            return [
                'device' => $device->fresh(),
                'command' => $command,
                'topic' => $topic,
                'payload' => $payload,
                'log_id' => $log->id,
            ];
        });
    }

    /**
     * @param  array<string, array<string, mixed>>  $paramDefs
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function validateParams(array $paramDefs, array $params): array
    {
        $out = [];

        foreach ($paramDefs as $name => $rules) {
            $required = (bool) ($rules['required'] ?? false);
            $has = array_key_exists($name, $params) && $params[$name] !== null && $params[$name] !== '';

            if (! $has) {
                if (array_key_exists('default', $rules)) {
                    $out[$name] = $rules['default'];

                    continue;
                }
                if ($required) {
                    throw new InvalidArgumentException("Paramètre requis manquant : {$name}");
                }

                continue;
            }

            $value = $params[$name];
            $type = $rules['type'] ?? 'string';

            $out[$name] = match ($type) {
                'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $value,
                'integer', 'int' => $this->validateInt($name, $value, $rules),
                'number', 'float' => $this->validateFloat($name, $value, $rules),
                default => $this->validateString($name, $value, $rules),
            };

            if (isset($rules['enum']) && is_array($rules['enum']) && ! in_array($out[$name], $rules['enum'], true)) {
                throw new InvalidArgumentException(
                    "Paramètre {$name} invalide. Valeurs : ".implode(', ', $rules['enum'])
                );
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function validateInt(string $name, mixed $value, array $rules): int
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Paramètre {$name} doit être un entier.");
        }
        $int = (int) $value;
        if (isset($rules['min']) && $int < (int) $rules['min']) {
            throw new InvalidArgumentException("Paramètre {$name} < min {$rules['min']}.");
        }
        if (isset($rules['max']) && $int > (int) $rules['max']) {
            throw new InvalidArgumentException("Paramètre {$name} > max {$rules['max']}.");
        }

        return $int;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function validateFloat(string $name, mixed $value, array $rules): float
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException("Paramètre {$name} doit être un nombre.");
        }
        $float = (float) $value;
        if (isset($rules['min']) && $float < (float) $rules['min']) {
            throw new InvalidArgumentException("Paramètre {$name} < min {$rules['min']}.");
        }
        if (isset($rules['max']) && $float > (float) $rules['max']) {
            throw new InvalidArgumentException("Paramètre {$name} > max {$rules['max']}.");
        }

        return $float;
    }

    /**
     * @param  array<string, mixed>  $rules
     */
    private function validateString(string $name, mixed $value, array $rules): string
    {
        $str = is_string($value) ? $value : (string) $value;
        if (isset($rules['max']) && mb_strlen($str) > (int) $rules['max']) {
            throw new InvalidArgumentException("Paramètre {$name} trop long (max {$rules['max']}).");
        }

        return $str;
    }

    /**
     * @param  array<string, mixed>|string  $template
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>|string
     */
    private function buildPayload(array|string $template, array $params): array|string
    {
        if (is_string($template)) {
            return $this->interpolateString($template, $params, optional: false) ?? '';
        }

        return $this->interpolateArray($template, $params);
    }

    /**
     * @param  array<string, mixed>  $template
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function interpolateArray(array $template, array $params): array
    {
        $out = [];

        foreach ($template as $key => $value) {
            if (is_array($value)) {
                $out[$key] = $this->interpolateArray($value, $params);

                continue;
            }

            if (! is_string($value)) {
                $out[$key] = $value;

                continue;
            }

            if (preg_match('/^\{\{(\w+)\?\}\}$/', $value, $m)) {
                $name = $m[1];
                if (! array_key_exists($name, $params) || $params[$name] === null || $params[$name] === '') {
                    continue;
                }
                $out[$key] = $params[$name];

                continue;
            }

            if (preg_match('/^\{\{(\w+)(?:\|([^}]+))?\}\}$/', $value, $m)) {
                $name = $m[1];
                $default = $m[2] ?? null;
                if (array_key_exists($name, $params)) {
                    $out[$key] = $params[$name];
                } elseif ($default !== null) {
                    $out[$key] = $this->coerceScalar($default);
                }

                continue;
            }

            $interpolated = $this->interpolateString($value, $params, optional: false);
            if ($interpolated !== null) {
                $out[$key] = $interpolated;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function interpolateString(string $template, array $params, bool $optional): ?string
    {
        $missingOptional = false;

        $result = preg_replace_callback(
            '/\{\{(\w+)(\?)?(?:\|([^}]+))?\}\}/',
            function (array $m) use ($params, &$missingOptional): string {
                $name = $m[1];
                $isOptional = ($m[2] ?? '') === '?';
                $default = $m[3] ?? null;

                if (array_key_exists($name, $params) && $params[$name] !== null && $params[$name] !== '') {
                    $v = $params[$name];

                    return is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
                }

                if ($default !== null) {
                    return $default;
                }

                if ($isOptional) {
                    $missingOptional = true;

                    return '';
                }

                return '';
            },
            $template
        );

        if ($optional && $missingOptional && trim((string) $result) === '') {
            return null;
        }

        return (string) $result;
    }

    private function coerceScalar(string $value): mixed
    {
        return match (strtolower($value)) {
            'true' => true,
            'false' => false,
            default => is_numeric($value) ? (str_contains($value, '.') ? (float) $value : (int) $value) : $value,
        };
    }

    /**
     * @param  array<string, mixed>  $definition
     */
    private function resolveTopic(Device $device, array $definition): string
    {
        $override = $definition['topic'] ?? null;

        if (is_string($override) && $override !== '') {
            return $override;
        }

        return (string) $device->mqtt_topic;
    }
}
