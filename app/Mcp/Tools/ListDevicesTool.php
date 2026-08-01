<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\RespondsWithJson;
use App\Services\DevicePermissionService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ListDevices')]
#[Description('Liste les dispositifs que l\'utilisateur authentifié a le droit de piloter.')]
#[IsReadOnly]
class ListDevicesTool extends Tool
{
    use RespondsWithJson;

    public function __construct(
        private readonly DevicePermissionService $permissions,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Authentification requise.');
        }

        $devices = $this->permissions->listAccessibleDevices($user);

        return $this->jsonResponse([
            'ok' => true,
            'count' => $devices->count(),
            'devices' => $devices->map(fn ($device) => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
                'mqtt_topic' => $device->mqtt_topic,
                'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                'status' => $device->status,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
