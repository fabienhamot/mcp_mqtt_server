<?php

namespace App\Http\Controllers\Api;

use App\Enums\DisplayAction;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\User;
use App\Services\DevicePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DevicePermissionController extends Controller
{
    public function __construct(
        private readonly DevicePermissionService $permissions,
    ) {}

    public function index(Request $request, Device $device): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        return response()->json(
            $device->permissions()->with('user:id,name,email')->get()
        );
    }

    public function store(Request $request, Device $device): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'allowed_actions' => ['required', 'array', 'min:1'],
            'allowed_actions.*' => ['string', 'in:'.implode(',', DisplayAction::controllableValues())],
        ]);

        $user = User::query()->findOrFail($validated['user_id']);
        $permission = $this->permissions->grant($user, $device, $validated['allowed_actions']);

        return response()->json($permission->load('user:id,name,email'), 201);
    }

    public function destroy(Request $request, Device $device, User $user): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $device->permissions()->where('user_id', $user->id)->delete();

        return response()->json(null, 204);
    }
}
