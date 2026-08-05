<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\User;
use App\Services\DevicePermissionService;
use App\Services\MqttPublisher;
use App\Support\DeviceCapabilityCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Tests\TestCase;

class MobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->artisan('migrate', ['--force' => true]);
        $this->artisan('passport:keys', ['--force' => true]);

        Client::factory()->asPersonalAccessTokenClient()->create([
            'name' => 'Test Personal Access',
        ]);
    }

    public function test_me_devices_lists_only_accessible_devices(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        $mine = Device::factory()->create(['name' => 'Ma prise']);
        Device::factory()->create(['name' => 'Autre prise']);

        app(DevicePermissionService::class)->grant($user, $mine, $mine->commandNames());

        Passport::actingAs($user);

        $response = $this->getJson('/api/me/devices');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('devices.0.id', $mine->id)
            ->assertJsonPath('devices.0.name', 'Ma prise')
            ->assertJsonStructure([
                'devices' => [[
                    'id', 'name', 'type', 'commands', 'capabilities', 'connectivity', 'last_seen_at', 'status',
                ]],
            ]);
    }

    public function test_me_device_show_forbidden_without_permission(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();

        Passport::actingAs($user);

        $this->getJson("/api/me/devices/{$device->id}")
            ->assertForbidden();
    }

    public function test_invoke_command_requires_permission(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create([
            'capabilities' => [
                'commands' => [
                    'power_on' => [
                        'description' => 'ON',
                        'params' => [],
                        'payload' => 'ON',
                    ],
                ],
            ],
        ]);

        Passport::actingAs($user);

        $this->postJson("/api/me/devices/{$device->id}/commands", [
            'command' => 'power_on',
            'params' => [],
        ])->assertForbidden();
    }

    public function test_invoke_command_publishes_mqtt(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create([
            'mqtt_topic' => 'cmnd/tasmota_test/POWER1',
            'capabilities' => [
                'commands' => [
                    'power_on' => [
                        'description' => 'ON',
                        'params' => [],
                        'payload' => 'ON',
                    ],
                ],
            ],
        ]);

        app(DevicePermissionService::class)->grant($user, $device, ['power_on']);

        $mqtt = $this->createMock(MqttPublisher::class);
        $mqtt->expects($this->once())
            ->method('publishRaw')
            ->with('cmnd/tasmota_test/POWER1', 'ON', false);
        $this->app->instance(MqttPublisher::class, $mqtt);

        Passport::actingAs($user);

        $response = $this->postJson("/api/me/devices/{$device->id}/commands", [
            'command' => 'power_on',
            'params' => [],
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('command', 'power_on')
            ->assertJsonPath('mqtt_topic', 'cmnd/tasmota_test/POWER1')
            ->assertJsonPath('payload', 'ON');
    }

    public function test_auth_token_returns_bearer_token(): void
    {
        $user = User::factory()->create([
            'email' => 'mobile@test.local',
            'password' => Hash::make('secret'),
        ]);

        $response = $this->postJson('/api/auth/token', [
            'email' => 'mobile@test.local',
            'password' => 'secret',
            'device_name' => 'iPhone Test',
        ]);

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'mobile@test.local')
            ->assertJsonStructure(['access_token']);
    }

    public function test_auth_token_rejects_invalid_credentials(): void
    {
        User::factory()->create([
            'email' => 'mobile@test.local',
            'password' => Hash::make('secret'),
        ]);

        $this->postJson('/api/auth/token', [
            'email' => 'mobile@test.local',
            'password' => 'wrong',
            'device_name' => 'iPhone Test',
        ])->assertUnprocessable();
    }

    public function test_me_display_logs_respects_device_access(): void
    {
        $user = User::factory()->create();
        $device = Device::factory()->create();
        app(DevicePermissionService::class)->grant($user, $device, $device->commandNames());

        Passport::actingAs($user);

        $this->getJson('/api/me/display-logs')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_me_password_update(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-secret'),
        ]);

        Passport::actingAs($user);

        $this->putJson('/api/me/password', [
            'current_password' => 'old-secret',
            'password' => 'new-secret-12',
            'password_confirmation' => 'new-secret-12',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertTrue(Hash::check('new-secret-12', $user->fresh()->password));
    }

    public function test_me_tokens_create_and_revoke(): void
    {
        $user = User::factory()->create();

        Passport::actingAs($user);

        $create = $this->postJson('/api/me/tokens', ['name' => 'Test Phone']);
        $create->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['token' => ['access_token']]);

        $list = $this->getJson('/api/me/tokens');
        $list->assertOk()->assertJsonPath('count', 1);
        $tokenId = $list->json('tokens.0.id');

        $this->deleteJson("/api/me/tokens/{$tokenId}")
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->getJson('/api/me/tokens')->assertJsonPath('count', 0);
    }

    public function test_me_profile_update(): void
    {
        $user = User::factory()->create(['name' => 'Ancien']);

        Passport::actingAs($user);

        $this->patchJson('/api/me', ['name' => 'Nouveau'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nouveau');
    }
}
