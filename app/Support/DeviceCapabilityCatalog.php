<?php

namespace App\Support;

/**
 * Catalogue de commandes MQTT + éléments d'état (status_items) par device.
 *
 * Structure :
 * {
 *   "commands": { ... },
 *   "status_items": [
 *     {
 *       "key": "door",
 *       "label": "Porte",
 *       "topic": "shelly…/status/input:0",
 *       "path": "state",
 *       "map": {"true": "open", "false": "closed"}
 *     }
 *   ]
 * }
 */
final class DeviceCapabilityCatalog
{
    /**
     * @return array{commands: array<string, array<string, mixed>>, status_items: list<array<string, mixed>>}
     */
    public static function ledDisplay(): array
    {
        return [
            'commands' => [
                'text' => [
                    'description' => 'Afficher du texte sur l\'écran LED',
                    'params' => [
                        'text' => ['type' => 'string', 'required' => true, 'max' => 2048],
                        'duration' => ['type' => 'integer', 'required' => false, 'min' => 0, 'max' => 86400],
                        'priority' => ['type' => 'string', 'enum' => ['normal', 'high'], 'default' => 'normal'],
                    ],
                    'payload' => [
                        'type' => 'text',
                        'content' => '{{text}}',
                        'priority' => '{{priority}}',
                        'duration' => '{{duration?}}',
                    ],
                ],
                'image' => [
                    'description' => 'Afficher une image (URL http/https)',
                    'params' => [
                        'image_url' => ['type' => 'string', 'required' => true, 'max' => 2048],
                        'duration' => ['type' => 'integer', 'required' => false, 'min' => 0, 'max' => 86400],
                        'priority' => ['type' => 'string', 'enum' => ['normal', 'high'], 'default' => 'normal'],
                    ],
                    'payload' => [
                        'type' => 'image',
                        'content' => '{{image_url}}',
                        'priority' => '{{priority}}',
                        'duration' => '{{duration?}}',
                    ],
                ],
                'color' => [
                    'description' => 'Remplir l\'écran d\'une couleur',
                    'params' => [
                        'color' => ['type' => 'string', 'required' => true, 'max' => 32],
                        'duration' => ['type' => 'integer', 'required' => false, 'min' => 0, 'max' => 86400],
                    ],
                    'payload' => [
                        'type' => 'color',
                        'content' => '{{color}}',
                        'priority' => 'normal',
                        'duration' => '{{duration?}}',
                    ],
                ],
                'clear' => [
                    'description' => 'Effacer l\'écran',
                    'params' => [],
                    'payload' => [
                        'type' => 'clear',
                        'content' => '',
                        'priority' => 'normal',
                    ],
                ],
            ],
            'status_items' => [],
        ];
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>, status_items: list<array<string, mixed>>}
     */
    public static function relayExample(): array
    {
        return [
            'commands' => [
                'power' => [
                    'description' => 'Allumer ou éteindre le relais',
                    'retain' => true,
                    'params' => [
                        'on' => ['type' => 'boolean', 'required' => true],
                    ],
                    'payload' => [
                        'state' => '{{on}}',
                    ],
                ],
                'toggle' => [
                    'description' => 'Inverser l\'état du relais',
                    'params' => [],
                    'payload' => [
                        'action' => 'toggle',
                    ],
                ],
            ],
            'status_items' => [],
        ];
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>, status_items: list<array<string, mixed>>}
     */
    public static function empty(): array
    {
        return ['commands' => [], 'status_items' => []];
    }

    /**
     * @param  array<string, mixed>|null  $capabilities
     * @return array{commands: array<string, array<string, mixed>>, status_items: list<array<string, mixed>>}
     */
    public static function normalize(?array $capabilities, string $type = 'generic'): array
    {
        if ($capabilities !== null && isset($capabilities['commands']) && is_array($capabilities['commands'])) {
            return [
                'commands' => $capabilities['commands'],
                'status_items' => self::normalizeStatusItems($capabilities['status_items'] ?? []),
            ];
        }

        return match ($type) {
            'led_display' => self::ledDisplay(),
            'relay' => self::relayExample(),
            default => self::empty(),
        };
    }

    /**
     * @param  mixed  $items
     * @return list<array{key: string, label: string, topic: string, path: ?string, map: array<string, string>}>
     */
    public static function normalizeStatusItems(mixed $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $out = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $key = trim((string) ($item['key'] ?? ''));
            $topic = trim((string) ($item['topic'] ?? ''));

            if ($key === '' || $topic === '') {
                continue;
            }

            $map = [];
            if (isset($item['map']) && is_array($item['map'])) {
                foreach ($item['map'] as $from => $to) {
                    $map[(string) $from] = (string) $to;
                }
            }

            $path = $item['path'] ?? null;
            $path = is_string($path) && trim($path) !== '' ? trim($path) : null;

            $out[] = [
                'key' => $key,
                'label' => trim((string) ($item['label'] ?? $key)) ?: $key,
                'topic' => $topic,
                'path' => $path,
                'map' => $map,
            ];
        }

        return $out;
    }

    /**
     * @param  array{commands?: array<string, array<string, mixed>>, status_items?: list<array<string, mixed>>}  $capabilities
     * @return list<string>
     */
    public static function commandNames(array $capabilities): array
    {
        return array_keys($capabilities['commands'] ?? []);
    }
}
