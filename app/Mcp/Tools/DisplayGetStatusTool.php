<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\RespondsWithJson;
use App\Services\DisplayCommandService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use RuntimeException;

#[Name('DisplayGetStatus')]
#[Description('Retourne le dernier état connu (status JSON + last_seen_at) d\'un device LED.')]
#[IsReadOnly]
class DisplayGetStatusTool extends Tool
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
        ]);

        try {
            $result = $this->commands->status($user, (int) $validated['device_id']);

            return $this->jsonResponse([
                'ok' => true,
                'device_id' => $result['device']->id,
                'name' => $result['device']->name,
                'type' => $result['device']->type,
                'mqtt_topic' => $result['device']->mqtt_topic,
                'status' => $result['status'],
                'last_seen_at' => $result['last_seen_at'],
            ]);
        } catch (RuntimeException $e) {
            return Response::error($e->getMessage());
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
        ];
    }
}
