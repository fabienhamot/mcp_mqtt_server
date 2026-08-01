<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\RespondsWithJson;
use App\Enums\DisplayPriority;
use App\Services\DisplayCommandService;
use App\Services\DisplayPayload;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use RuntimeException;
use Throwable;

#[Name('DisplaySendImage')]
#[Description('Affiche une image sur un écran LED via une URL http(s). Publie {type:image, content:url, duration?, priority}.')]
class DisplaySendImageTool extends Tool
{
    use RespondsWithJson;

    public function __construct(
        private readonly DisplayCommandService $commands,
    ) {}

    public function handle(Request $request): Response
    {
        $user = $request->user();

        if ($user === null) {
            return Response::error('Authentification requise.');
        }

        $validated = $request->validate([
            'device_id' => ['required', 'integer', 'min:1'],
            'image_url' => ['required', 'url', 'regex:/^https?:\/\//i', 'max:2048'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'priority' => ['nullable', 'string', 'in:normal,high'],
        ], [
            'image_url.regex' => 'image_url doit être une URL http(s), jamais de binaire.',
        ]);

        try {
            $priority = DisplayPriority::from($validated['priority'] ?? DisplayPriority::Normal->value);
            $payload = DisplayPayload::image(
                $validated['image_url'],
                $validated['duration'] ?? null,
                $priority,
            );

            $result = $this->commands->send($user, (int) $validated['device_id'], $payload);

            return $this->jsonResponse([
                'ok' => true,
                'message' => 'Image publiée sur MQTT.',
                'device_id' => $result['device']->id,
                'mqtt_topic' => $result['device']->mqtt_topic,
                'payload' => $result['payload'],
                'log_id' => $result['log_id'],
            ]);
        } catch (RuntimeException $e) {
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
                ->description('Identifiant du device LED.')
                ->required(),
            'image_url' => $schema->string()
                ->description('URL http(s) de l\'image (jamais de binaire).')
                ->required(),
            'duration' => $schema->integer()
                ->description('Durée d\'affichage en secondes (optionnel).'),
            'priority' => $schema->string()
                ->enum(['normal', 'high'])
                ->description('Priorité du message.')
                ->default('normal'),
        ];
    }
}
