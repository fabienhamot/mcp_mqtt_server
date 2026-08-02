<?php

namespace Tests\Unit;

use App\Models\Device;
use App\Services\MqttDeviceStatusService;
use App\Support\DeviceCapabilityCatalog;
use Tests\TestCase;

class MqttDeviceStatusServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        $this->artisan('migrate', ['--force' => true]);
    }

    public function test_extract_tasmota_slug(): void
    {
        $svc = new MqttDeviceStatusService;

        $this->assertSame('tasmota_180974', $svc->extractDeviceSlug('tele/tasmota_180974/STATE'));
        $this->assertSame('tasmota_180974', $svc->extractDeviceSlug('stat/tasmota_180974/POWER'));
        $this->assertSame('tasmota_180974', $svc->extractDeviceSlug('cmnd/tasmota_180974/POWER'));
        $this->assertNull($svc->extractDeviceSlug('display/led/status'));
    }

    public function test_lwt_online_and_offline(): void
    {
        $device = $this->makeTasmotaDevice();
        $svc = new MqttDeviceStatusService;

        $svc->handle('tele/tasmota_180974/LWT', 'Online');
        $device->refresh();
        $this->assertTrue($device->isOnline());
        $this->assertSame('online', $device->connectivityLabel());
        $this->assertSame('Online', $device->status['lwt']);

        $svc->handle('tele/tasmota_180974/LWT', 'Offline');
        $device->refresh();
        $this->assertFalse($device->isOnline());
        $this->assertSame('offline', $device->connectivityLabel());
        $this->assertSame('Offline', $device->status['lwt']);
    }

    public function test_state_json_updates_last_seen(): void
    {
        $device = $this->makeTasmotaDevice();
        $svc = new MqttDeviceStatusService;

        $svc->handle('tele/tasmota_180974/STATE', '{"POWER":"ON","Wifi":{"SSId":"home"}}');
        $device->refresh();

        $this->assertTrue($device->isOnline());
        $this->assertSame('ON', $device->status['POWER']);
        $this->assertSame('Online', $device->status['lwt']);
    }

    public function test_power_plain_text(): void
    {
        $device = $this->makeTasmotaDevice();
        $svc = new MqttDeviceStatusService;

        $svc->handle('stat/tasmota_180974/POWER', 'ON');
        $device->refresh();

        $this->assertTrue($device->isOnline());
        $this->assertSame('ON', $device->status['power']);
    }

    public function test_matches_via_mqtt_command_topic(): void
    {
        $device = Device::query()->create([
            'name' => 'Prise',
            'type' => 'relay',
            'capabilities' => DeviceCapabilityCatalog::relayExample(),
            'mqtt_topic' => 'cmnd/tasmota_AABBCC/POWER',
            'status_topic' => null,
            'status' => [],
        ]);

        $svc = new MqttDeviceStatusService;
        $found = $svc->findDevice('tele/tasmota_AABBCC/LWT');

        $this->assertNotNull($found);
        $this->assertTrue($found->is($device));
    }

    public function test_exact_status_topic_json_still_works(): void
    {
        $device = Device::query()->create([
            'name' => 'LED',
            'type' => 'led_display',
            'capabilities' => DeviceCapabilityCatalog::ledDisplay(),
            'mqtt_topic' => 'display/led',
            'status_topic' => 'display/led/status',
            'status' => [],
        ]);

        $svc = new MqttDeviceStatusService;
        $svc->handle('display/led/status', '{"state":"idle"}');
        $device->refresh();

        $this->assertSame('idle', $device->status['state']);
        $this->assertTrue($device->isOnline());
    }

    private function makeTasmotaDevice(): Device
    {
        return Device::query()->create([
            'name' => 'NOUS A1T',
            'type' => 'relay',
            'capabilities' => DeviceCapabilityCatalog::relayExample(),
            'mqtt_topic' => 'cmnd/tasmota_180974/POWER',
            'status_topic' => 'tele/tasmota_180974/STATE',
            'status' => [],
            'last_seen_at' => null,
        ]);
    }
}
