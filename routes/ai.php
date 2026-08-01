<?php

use App\Mcp\Servers\LedDisplayServer;
use Laravel\Mcp\Facades\Mcp;

/*
|--------------------------------------------------------------------------
| MCP Routes
|--------------------------------------------------------------------------
|
| Doc officielle : https://laravel.com/docs/mcp
| OAuth MCP : Mcp::oauthRoutes() + middleware auth:api (Passport).
|
*/

Mcp::oauthRoutes();

Mcp::web('/mcp/led-display', LedDisplayServer::class)
    ->middleware(['auth:api']);
