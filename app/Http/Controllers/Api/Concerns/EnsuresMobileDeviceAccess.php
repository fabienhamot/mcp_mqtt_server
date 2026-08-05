<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Models\Device;
use App\Models\User;
use Illuminate\Http\Request;

trait EnsuresMobileDeviceAccess
{
    protected function ensureDeviceAccess(Request $request, Device $device): void
    {
        $user = $request->user();

        abort_if($user === null, 401);

        if ($user->is_admin) {
            return;
        }

        $hasAccess = $user->devices()->where('devices.id', $device->id)->exists();

        abort_unless($hasAccess, 403, 'Accès refusé.');
    }

    /**
     * @return list<int>
     */
    protected function accessibleDeviceIds(User $user): array
    {
        if ($user->is_admin) {
            return Device::query()->pluck('id')->all();
        }

        return $user->devices()->pluck('devices.id')->all();
    }
}
