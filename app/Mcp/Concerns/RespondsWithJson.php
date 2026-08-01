<?php

namespace App\Mcp\Concerns;

use JsonException;
use Laravel\Mcp\Response;

trait RespondsWithJson
{
    /**
     * Réponse MCP JSON (via Response::text — Response::json est @internal dans laravel/mcp).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws JsonException
     */
    protected function jsonResponse(array $data): Response
    {
        return Response::text(json_encode(
            $data,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        ));
    }
}
