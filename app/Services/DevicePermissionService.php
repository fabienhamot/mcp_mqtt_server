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
        if ($action === DisplayAction::Status) {
            return;
        }

        /** @var list<string> $allowed */
        $allowed = $permission->allowed_actions ?? [];

        if (! in_array($action->value, $allowed, true)) {
            throw new RuntimeException(
                "Permission refusée : action « {$action->value} » non autorisée sur le device #{$device->id}."
            );
        }
    }

    /**
     * @throws RuntimeException
     */
    public function authorizeDevice(User $user, int $deviceId, DisplayAction $action): Device
    {
        $device = Device::query()->find($deviceId);

        if ($device === null) {
            throw new RuntimeException("Device #{$deviceId} introuvable.");
        }

        $this->assertCan($user, $device, $action);

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
        $normalized = array_values(array_unique(array_intersect(
            $allowedActions,
            DisplayAction::controllableValues()
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
