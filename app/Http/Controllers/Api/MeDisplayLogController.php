<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresMobileDeviceAccess;
use App\Http\Controllers\Controller;
use App\Models\DisplayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeDisplayLogController extends Controller
{
    use EnsuresMobileDeviceAccess;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $deviceIds = $this->accessibleDeviceIds($user);

        if ($deviceIds === []) {
            return response()->json([
                'ok' => true,
                'data' => [],
                'meta' => ['total' => 0],
            ]);
        }

        $limit = min(max((int) $request->input('limit', 20), 1), 50);

        $query = DisplayLog::query()
            ->whereIn('device_id', $deviceIds)
            ->with(['device:id,name'])
            ->latest('created_at');

        if ($request->filled('device_id')) {
            $deviceId = (int) $request->input('device_id');
            abort_unless(in_array($deviceId, $deviceIds, true), 403, 'Accès refusé.');
            $query->where('device_id', $deviceId);
        }

        $logs = $query->limit($limit)->get();

        return response()->json([
            'ok' => true,
            'data' => $logs->map(fn (DisplayLog $log) => [
                'id' => $log->id,
                'device_id' => $log->device_id,
                'device_name' => $log->device?->name,
                'payload' => $log->payload,
                'created_at' => $log->created_at?->toIso8601String(),
            ])->values()->all(),
            'meta' => [
                'total' => $logs->count(),
                'limit' => $limit,
            ],
        ]);
    }
}
