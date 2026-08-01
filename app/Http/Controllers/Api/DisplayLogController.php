<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DisplayLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisplayLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless($request->user()?->is_admin, 403);

        $query = DisplayLog::query()
            ->with(['device:id,name', 'user:id,name,email'])
            ->latest('created_at');

        if ($request->filled('device_id')) {
            $query->where('device_id', (int) $request->input('device_id'));
        }

        return response()->json($query->paginate(50));
    }
}
