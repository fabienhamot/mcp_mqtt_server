<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DisplayClearTool;
use App\Mcp\Tools\DisplayGetStatusTool;
use App\Mcp\Tools\DisplaySendImageTool;
use App\Mcp\Tools\DisplaySendTextTool;
use App\Mcp\Tools\DisplaySetColorTool;
use App\Mcp\Tools\ListDevicesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('LED Display Server')]
#[Version('1.0.0')]
#[Instructions('Serveur central domotique pour piloter des écrans LED via MQTT. Authentification OAuth Passport requise. Utilisez ListDevices pour découvrir les devices autorisés, puis DisplaySendText / DisplaySendImage / DisplaySetColor / DisplayClear pour commander. DisplayGetStatus retourne le dernier état connu.')]
class LedDisplayServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListDevicesTool::class,
        DisplaySendTextTool::class,
        DisplaySendImageTool::class,
        DisplaySetColorTool::class,
        DisplayClearTool::class,
        DisplayGetStatusTool::class,
    ];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Prompt>>
     */
    protected array $prompts = [];
}
