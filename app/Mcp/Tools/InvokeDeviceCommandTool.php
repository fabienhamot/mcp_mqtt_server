<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\RespondsWithJson;
use App\Services\DeviceCommandService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

#[Name('InvokeDeviceCommand')]
#[Description('Envoie une commande MQTT générique à un dispositif. Découvrez les commandes et params via ListDevices (champ capabilities). Ex. command=text params={"text":"Hello"} pour un led_display, ou command=power params={"on":true} pour un relais.')]
#[IsIdempotent]
class InvokeDeviceCommandTool extends Tool
{
    use RespondsWithJson;

    public function __construct(
        private readonly DeviceCommandService $commands,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Authentification requise.');
        }

        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'min:1'],
            'command' => ['required', 'string', 'max:100'],
            'params' => ['nullable', 'array'],
        ], [
            'device_id.required' => 'Indiquez device_id (utilisez ListDevices).',
            'command.required' => 'Indiquez le nom de commande (voir capabilities du device).',
        ]);

        /** @var array<string, mixed> $params */
        $params = $validated['params'] ?? [];

        try {
            $result = $this->commands->invoke(
                $user,
                (int) $validated['device_id'],
                (string) $validated['command'],
                $params,
            );

            return $this->jsonResponse([
                'ok' => true,
                'message' => "Commande « {$result['command']} » publiée sur MQTT.",
                'device_id' => $result['device']->id,
                'mqtt_topic' => $result['topic'],
                'command' => $result['command'],
                'payload' => $result['payload'],
                'log_id' => $result['log_id'],
            ]);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return Response::error($e->getMessage());
        } catch (Throwable $e) {
            return Response::error('Échec publication MQTT : '.$e->getMessage());
        }
    }

    /**
     * @return array<string, \Illuminate\JsonSchema\Types\Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'device_id' => $schema->integer()
                ->description('Identifiant du dispositif (ListDevices).')
                ->required(),
            'command' => $schema->string()
                ->description('Nom de la commande (clé dans capabilities.commands).')
                ->required(),
            'params' => $schema->object()
                ->description('Paramètres de la commande (objet JSON). Voir capabilities.commands.<command>.params.'),
        ];
    }
}
