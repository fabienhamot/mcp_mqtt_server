<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\DisplayClearTool;
use App\Mcp\Tools\DisplayGetStatusTool;
use App\Mcp\Tools\DisplaySendImageTool;
use App\Mcp\Tools\DisplaySendTextTool;
use App\Mcp\Tools\DisplaySetColorTool;
use App\Mcp\Tools\InvokeDeviceCommandTool;
use App\Mcp\Tools\ListDevicesTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('LED Display Server')]
#[Version('1.1.0')]
#[Instructions('Serveur domotique MQTT. Auth OAuth Passport requise. 1) ListDevices pour découvrir id, type et capabilities.commands. 2) InvokeDeviceCommand(device_id, command, params) pour toute commande déclarée. Les tools DisplaySendText/Image/SetColor/Clear restent disponibles pour les écrans led_display. DisplayGetStatus lit le dernier statut connu.')]
class LedDisplayServer extends Server
{
    /**
     * @var array<int, class-string<\Laravel\Mcp\Server\Tool>>
     */
    protected array $tools = [
        ListDevicesTool::class,
        InvokeDeviceCommandTool::class,
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
