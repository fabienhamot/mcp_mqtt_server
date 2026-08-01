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
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use RuntimeException;
use Throwable;

#[Name('DisplaySendText')]
#[Description('Affiche un texte sur un écran LED. Publie un payload MQTT {type:text, content, duration?, priority}.')]
#[IsIdempotent]
class DisplaySendTextTool extends Tool
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
            'text' => ['required', 'string', 'max:2048'],
            'color' => ['nullable', 'string', 'max:32'],
            'scroll' => ['nullable', 'boolean'],
            'duration' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'priority' => ['nullable', 'string', 'in:normal,high'],
        ], [
            'device_id.required' => 'Indiquez device_id (utilisez ListDevices pour le découvrir).',
            'text.required' => 'Indiquez le texte à afficher.',
            'priority.in' => 'priority doit être "normal" ou "high".',
        ]);

        // color / scroll sont acceptés pour l'agent mais hors contrat MQTT Pi :
        // seul le format strict {type,content,duration?,priority} est publié.
        // TODO: si le firmware Pi ajoute color/scroll pour type=text, les inclure ici.

        try {
            $priority = DisplayPriority::from($validated['priority'] ?? DisplayPriority::Normal->value);
            $payload = DisplayPayload::text(
                $validated['text'],
                $validated['duration'] ?? null,
                $priority,
            );

            $result = $this->commands->send($user, (int) $validated['device_id'], $payload);

            return $this->jsonResponse([
                'ok' => true,
                'message' => 'Texte publié sur MQTT.',
                'device_id' => $result['device']->id,
                'mqtt_topic' => $result['device']->mqtt_topic,
                'payload' => $result['payload'],
                'log_id' => $result['log_id'],
                'ignored_optional' => array_filter([
                    'color' => $validated['color'] ?? null,
                    'scroll' => $validated['scroll'] ?? null,
                ]),
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
            'text' => $schema->string()
                ->description('Texte à afficher sur l\'écran.')
                ->required(),
            'color' => $schema->string()
                ->description('Couleur optionnelle (non publiée MQTT tant que le firmware Pi ne la gère pas pour type=text). Hex ou r,g,b.'),
            'scroll' => $schema->boolean()
                ->description('Défilement optionnel (non publié MQTT pour l\'instant).'),
            'duration' => $schema->integer()
                ->description('Durée d\'affichage en secondes (optionnel).'),
            'priority' => $schema->string()
                ->enum(['normal', 'high'])
                ->description('Priorité du message.')
                ->default('normal'),
        ];
    }
}
