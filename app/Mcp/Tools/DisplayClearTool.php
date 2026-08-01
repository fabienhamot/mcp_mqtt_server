<?php

namespace App\Mcp\Tools;

use App\Mcp\Concerns\RespondsWithJson;
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

#[Name('DisplayClear')]
#[Description('Efface l\'écran LED. Publie {type:clear, content:"", priority:normal}.')]
class DisplayClearTool extends Tool
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
            $result = $this->commands->send(
                $user,
                (int) $validated['device_id'],
                DisplayPayload::clear(),
            );

            return $this->jsonResponse([
                'ok' => true,
                'message' => 'Clear publié sur MQTT.',
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
        ];
    }
}
