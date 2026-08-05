<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\EnsuresMobileDeviceAccess;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\DeviceCommandService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class MeDeviceCommandController extends Controller
{
    use EnsuresMobileDeviceAccess;

    public function __construct(
        private readonly DeviceCommandService $commands,
    ) {}

    public function store(Request $request, Device $device): JsonResponse
    {
        $this->ensureDeviceAccess($request, $device);

        $validated = $request->validate([
            'command' => ['required', 'string', 'max:100'],
            'params' => ['nullable', 'array'],
        ]);

        /** @var array<string, mixed> $params */
        $params = $validated['params'] ?? [];

        try {
            $result = $this->commands->invoke(
                $request->user(),
                $device->id,
                (string) $validated['command'],
                $params,
            );

            return response()->json([
                'ok' => true,
                'message' => "Commande « {$result['command']} » publiée sur MQTT.",
                'device_id' => $result['device']->id,
                'command' => $result['command'],
                'mqtt_topic' => $result['topic'],
                'payload' => $result['payload'],
                'log_id' => $result['log_id'],
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (RuntimeException $e) {
            $status = str_contains($e->getMessage(), 'introuvable') ? 404 : 403;

            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], $status);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Échec publication MQTT : '.$e->getMessage(),
            ], 500);
        }
    }
}
