<?php

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    protected $model = Device::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $slug = fake()->unique()->slug(2);

        return [
            'name' => 'LED '.$slug,
            'type' => 'led_display',
            'mqtt_topic' => 'display/led/'.$slug,
            'status' => ['state' => 'idle'],
            'last_seen_at' => null,
        ];
    }
}
