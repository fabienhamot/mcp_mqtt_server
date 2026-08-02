<?php

namespace App\Services;

use App\Enums\DisplayAction;
use App\Models\Device;
use App\Models\DeviceUserPermission;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class DevicePermissionService
{
    /**
     * @throws RuntimeException
     */
    public function assertCan(User $user, Device $device, DisplayAction $action): void
    {
        $this->assertCanCommand($user, $device, $action->value, displayLegacy: true);
    }

    /**
     * @throws RuntimeException
     */
    public function assertCanCommand(User $user, Device $device, string $command, bool $displayLegacy = false): void
    {
        if ($user->is_admin) {
            return;
        }

        $permission = DeviceUserPermission::query()
            ->where('user_id', $user->id)
            ->where('device_id', $device->id)
            ->first();

        if ($permission === null) {
            throw new RuntimeException(
                "Permission refusée : aucun accès au device #{$device->id} ({$device->name})."
            );
        }

        // Status : accessible dès qu'il y a une permission sur le device.
        if ($command === DisplayAction::Status->value || $command === 'status') {
            return;
        }

        // list n'est pas une commande MQTT
        if ($command === DisplayAction::List->value) {
            return;
        }

        /** @var list<string> $allowed */
        $allowed = $permission->allowed_actions ?? [];

        if (! in_array($command, $allowed, true)) {
            throw new RuntimeException(
                "Permission refusée : commande « {$command} » non autorisée sur le device #{$device->id}."
            );
        }

        if (! $displayLegacy) {
            $known = $device->commandNames();
            if ($known !== [] && ! in_array($command, $known, true)) {
                throw new RuntimeException(
                    "Commande « {$command} » absente des capabilities du device #{$device->id}."
                );
            }
        }
    }

    /**
     * @throws RuntimeException
     */
    public function authorizeDevice(User $user, int $deviceId, DisplayAction $action): Device
    {
        return $this->authorizeCommand($user, $deviceId, $action->value, displayLegacy: true);
    }

    /**
     * @throws RuntimeException
     */
    public function authorizeCommand(User $user, int $deviceId, string $command, bool $displayLegacy = false): Device
    {
        $device = Device::query()->find($deviceId);

        if ($device === null) {
            throw new RuntimeException("Device #{$deviceId} introuvable.");
        }

        $this->assertCanCommand($user, $device, $command, $displayLegacy);

        return $device;
    }

    /**
     * @return Collection<int, Device>
     */
    public function listAccessibleDevices(User $user): Collection
    {
        if ($user->is_admin) {
            return Device::query()->orderBy('name')->get();
        }

        return $user->devices()->orderBy('name')->get();
    }

    /**
     * @param  list<string>  $allowedActions
     */
    public function grant(User $user, Device $device, array $allowedActions): DeviceUserPermission
    {
        $allowedForDevice = $device->commandNames();
        if ($allowedForDevice === []) {
            $allowedForDevice = DisplayAction::controllableValues();
        }

        $normalized = array_values(array_unique(array_intersect(
            $allowedActions,
            $allowedForDevice
        )));

        $permission = DeviceUserPermission::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'device_id' => $device->id,
            ],
            [
                'allowed_actions' => $normalized,
            ],
        );

        Log::info('Device permission granted', [
            'user_id' => $user->id,
            'device_id' => $device->id,
            'allowed_actions' => $normalized,
        ]);

        return $permission;
    }
}
