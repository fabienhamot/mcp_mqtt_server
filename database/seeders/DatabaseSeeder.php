<?php

namespace Database\Seeders;

use App\Enums\DisplayAction;
use App\Models\Device;
use App\Models\User;
use App\Services\DevicePermissionService;
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
                'status' => [
                    'state' => 'idle',
                    'source' => 'seeder',
                ],
                'last_seen_at' => null,
            ],
        );

        /** @var DevicePermissionService $permissions */
        $permissions = app(DevicePermissionService::class);
        $permissions->grant($agent, $device, DisplayAction::controllableValues());
        $permissions->grant($admin, $device, DisplayAction::controllableValues());

        $this->command?->info('Seed OK — admin@led-display.local / agent@led-display.local (password)');
        $this->command?->info("Device démo #{$device->id} topic={$device->mqtt_topic}");
    }
}
