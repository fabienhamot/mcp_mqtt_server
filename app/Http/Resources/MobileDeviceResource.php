<?php

namespace App\Http\Resources;

use App\Models\Device;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Device
 */
class MobileDeviceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Device $device */
        $device = $this->resource;
        $capabilities = $device->resolvedCapabilities();
        $commands = [];

        foreach ($capabilities['commands'] as $name => $def) {
            if (! is_array($def)) {
                continue;
            }

            $commands[$name] = [
                'description' => $def['description'] ?? $name,
                'params' => $def['params'] ?? [],
                'retain' => (bool) ($def['retain'] ?? false),
            ];
        }

        $statusItems = array_map(static function (array $item): array {
            return [
                'key' => $item['key'],
                'label' => $item['label'],
                'topic' => $item['topic'],
                'path' => $item['path'],
                'map' => $item['map'],
            ];
        }, $capabilities['status_items']);

        return [
            'id' => $device->id,
            'name' => $device->name,
            'type' => $device->type,
            'mqtt_topic' => $device->mqtt_topic,
            'status_topic' => $device->resolvedStatusTopic(),
            'commands' => array_keys($commands),
            'capabilities' => [
                'commands' => $commands,
                'status_items' => $statusItems,
            ],
            'status_items' => $device->statusItemValues(),
            'connectivity' => $device->connectivityLabel(),
            'last_seen_at' => $device->last_seen_at?->toIso8601String(),
            'status' => $device->status,
        ];
    }
}
