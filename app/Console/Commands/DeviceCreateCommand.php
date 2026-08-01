<?php

namespace App\Console\Commands;

use App\Models\Device;
use Illuminate\Console\Command;

class DeviceCreateCommand extends Command
{
    protected $signature = 'device:create
        {name : Nom du device}
        {mqtt_topic : Topic MQTT (ex: display/led)}
        {--type=led_display : Type de device}';

    protected $description = 'Crée un device et affiche son id';

    public function handle(): int
    {
        $device = Device::query()->create([
            'name' => $this->argument('name'),
            'type' => $this->option('type'),
            'mqtt_topic' => $this->argument('mqtt_topic'),
            'status' => ['state' => 'unknown'],
        ]);

        $this->info("Device créé #{$device->id} — topic={$device->mqtt_topic}");

        return self::SUCCESS;
    }
}
