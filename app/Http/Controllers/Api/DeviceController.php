<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        return response()->json(Device::query()->orderBy('name')->paginate(50));
    }

    public function store(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:100'],
            'mqtt_topic' => ['required', 'string', 'max:255', 'unique:devices,mqtt_topic'],
            'status' => ['nullable', 'array'],
        ]);

        $device = Device::query()->create($validated);

        return response()->json($device, 201);
    }

    public function show(Request $request, Device $device): JsonResponse
    {
        $this->ensureAdminOrOwner($request, $device);

        return response()->json($device);
    }

    public function update(Request $request, Device $device): JsonResponse
    {
        $this->ensureAdmin($request);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'max:100'],
            'mqtt_topic' => ['sometimes', 'string', 'max:255', 'unique:devices,mqtt_topic,'.$device->id],
            'status' => ['nullable', 'array'],
        ]);

        $device->update($validated);

        return response()->json($device->fresh());
    }

    public function destroy(Request $request, Device $device): JsonResponse
    {
        $this->ensureAdmin($request);
        $device->delete();

        return response()->json(null, 204);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->is_admin, 403, 'Admin requis.');
    }

    private function ensureAdminOrOwner(Request $request, Device $device): void
    {
        $user = $request->user();

        if ($user?->is_admin) {
            return;
        }

        $hasAccess = $user?->devices()->where('devices.id', $device->id)->exists();

        abort_unless($hasAccess, 403, 'Accès refusé.');
    }
}
