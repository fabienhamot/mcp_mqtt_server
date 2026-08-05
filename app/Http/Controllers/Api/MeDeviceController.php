<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresMobileDeviceAccess;
use App\Http\Controllers\Controller;
use App\Http\Resources\MobileDeviceResource;
use App\Models\Device;
use App\Services\DevicePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeDeviceController extends Controller
{
    use EnsuresMobileDeviceAccess;

    public function __construct(
        private readonly DevicePermissionService $permissions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $devices = $this->permissions->listAccessibleDevices($user);

        return response()->json([
            'ok' => true,
            'count' => $devices->count(),
            'devices' => MobileDeviceResource::collection($devices)->resolve(),
        ]);
    }

    public function show(Request $request, Device $device): JsonResponse
    {
        $this->ensureDeviceAccess($request, $device);

        return response()->json([
            'ok' => true,
            'device' => (new MobileDeviceResource($device))->resolve(),
        ]);
    }
}
