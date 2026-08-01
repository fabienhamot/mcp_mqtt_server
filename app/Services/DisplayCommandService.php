<?php

namespace App\Services;

use App\Enums\DisplayAction;
use App\Models\Device;
use App\Models\DisplayLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DisplayCommandService
{
    public function __construct(
        private readonly MqttPublisher $mqtt,
        private readonly DevicePermissionService $permissions,
    ) {}

    /**
     * Autorise, publie sur MQTT et journalise.
     *
     * @return array{device: Device, payload: array<string, mixed>, log_id: int}
     *
     * @throws RuntimeException|Throwable
     */
    public function send(User $user, int $deviceId, DisplayPayload $payload): array
    {
        $device = $this->permissions->authorizeDevice($user, $deviceId, $payload->type);

        if (blank($device->mqtt_topic)) {
            throw new RuntimeException("Le device #{$device->id} n'a pas de mqtt_topic configuré.");
        }

        return DB::transaction(function () use ($user, $device, $payload) {
            $this->mqtt->publish($device->mqtt_topic, $payload);

            $log = DisplayLog::query()->create([
                'device_id' => $device->id,
                'user_id' => $user->id,
                'payload' => $payload->toArray(),
            ]);

            $device->forceFill([
                'status' => array_merge($device->status ?? [], [
                    'last_command' => $payload->toArray(),
                    'last_command_at' => now()->toIso8601String(),
                ]),
            ])->save();

            Log::info('Display command sent', [
                'user_id' => $user->id,
                'device_id' => $device->id,
                'topic' => $device->mqtt_topic,
                'payload' => $payload->toArray(),
                'log_id' => $log->id,
            ]);

            return [
                'device' => $device->fresh(),
                'payload' => $payload->toArray(),
                'log_id' => $log->id,
            ];
        });
    }

    /**
     * @return array{device: Device, status: mixed, last_seen_at: ?string}
     */
    public function status(User $user, int $deviceId): array
    {
        $device = $this->permissions->authorizeDevice($user, $deviceId, DisplayAction::Status);

        return [
            'device' => $device,
            'status' => $device->status,
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
        ];
    }
}
