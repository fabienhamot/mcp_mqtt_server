<?php

namespace App\Support;

/**
 * Catalogue de commandes MQTT par device.
 *
 * Structure :
 * {
 *   "commands": {
 *     "power": {
 *       "description": "Allumer / éteindre",
 *       "retain": false,
 *       "topic": null,
 *       "params": {
 *         "on": {"type": "boolean", "required": true}
 *       },
 *       "payload": {"state": "{{on}}"}
 *     }
 *   }
 * }
 *
 * Placeholders payload : {{param}}, {{param|default}}, {{param?}} (omis si vide).
 */
final class DeviceCapabilityCatalog
{
    /**
     * @return array{commands: array<string, array<string, mixed>>}
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
        ];
    }

    /**
     * Exemple relais MQTT générique.
     *
     * @return array{commands: array<string, array<string, mixed>>}
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
        ];
    }

    /**
     * @return array{commands: array<string, array<string, mixed>>}
     */
    public static function empty(): array
    {
        return ['commands' => []];
    }

    /**
     * @param  array<string, mixed>|null  $capabilities
     * @return array{commands: array<string, array<string, mixed>>}
     */
    public static function normalize(?array $capabilities, string $type = 'generic'): array
    {
        if ($capabilities !== null && isset($capabilities['commands']) && is_array($capabilities['commands'])) {
            return ['commands' => $capabilities['commands']];
        }

        return match ($type) {
            'led_display' => self::ledDisplay(),
            'relay' => self::relayExample(),
            default => self::empty(),
        };
    }

    /**
     * @param  array{commands: array<string, array<string, mixed>>}  $capabilities
     * @return list<string>
     */
    public static function commandNames(array $capabilities): array
    {
        return array_keys($capabilities['commands'] ?? []);
    }
}
