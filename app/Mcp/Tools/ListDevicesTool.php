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
#[Description('Liste les dispositifs autorisés avec leurs capabilities (commandes MQTT + params). Utilisez ensuite InvokeDeviceCommand, ou les tools Display* pour les écrans LED.')]
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
            'devices' => $devices->map(function ($device) {
                $capabilities = $device->resolvedCapabilities();
                $commands = [];

                foreach ($capabilities['commands'] as $name => $def) {
                    $commands[$name] = [
                        'description' => $def['description'] ?? $name,
                        'params' => $def['params'] ?? [],
                        'retain' => (bool) ($def['retain'] ?? false),
                    ];
                }

                return [
                    'id' => $device->id,
                    'name' => $device->name,
                    'type' => $device->type,
                    'mqtt_topic' => $device->mqtt_topic,
                    'status_topic' => $device->resolvedStatusTopic(),
                    'commands' => array_keys($commands),
                    'capabilities' => [
                        'commands' => $commands,
                        'status_items' => $capabilities['status_items'],
                    ],
                    'status_items' => $device->statusItemValues(),
                    'connectivity' => $device->connectivityLabel(),
                    'last_seen_at' => $device->last_seen_at?->toIso8601String(),
                    'status' => $device->status,
                ];
            })->values()->all(),
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
