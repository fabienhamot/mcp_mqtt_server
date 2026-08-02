<?php

namespace Database\Seeders;

use App\Models\Device;
use App\Models\User;
use App\Services\DevicePermissionService;
use App\Support\DeviceCapabilityCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@led-display.local'],
            [
                'name' => 'Admin LED',
                'password' => Hash::make('password'),
                'is_admin' => true,
            ],
        );

        $agent = User::query()->updateOrCreate(
            ['email' => 'agent@led-display.local'],
            [
                'name' => 'Agent IA',
                'password' => Hash::make('password'),
                'is_admin' => false,
            ],
        );

        $device = Device::query()->updateOrCreate(
            ['mqtt_topic' => 'display/led'],
            [
                'name' => 'LED Salon (démo)',
                'type' => 'led_display',
                'capabilities' => DeviceCapabilityCatalog::ledDisplay(),
                'status_topic' => 'display/led/status',
                'status' => [
                    'state' => 'idle',
                    'source' => 'seeder',
                ],
                'last_seen_at' => null,
            ],
        );

        $relay = Device::query()->updateOrCreate(
            ['mqtt_topic' => 'home/demo/relay'],
            [
                'name' => 'Relais démo',
                'type' => 'relay',
                'capabilities' => DeviceCapabilityCatalog::relayExample(),
                'status_topic' => 'home/demo/relay/status',
                'status' => ['state' => 'unknown'],
                'last_seen_at' => null,
            ],
        );

        /** @var DevicePermissionService $permissions */
        $permissions = app(DevicePermissionService::class);
        $permissions->grant($agent, $device, $device->commandNames());
        $permissions->grant($admin, $device, $device->commandNames());
        $permissions->grant($agent, $relay, $relay->commandNames());
        $permissions->grant($admin, $relay, $relay->commandNames());

        $this->command?->info('Seed OK — admin@led-display.local / agent@led-display.local (password)');
        $this->command?->info("Device LED #{$device->id} topic={$device->mqtt_topic}");
        $this->command?->info("Device Relais #{$relay->id} topic={$relay->mqtt_topic}");
    }
}
