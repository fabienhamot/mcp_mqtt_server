<?php

namespace Tests\Unit;

use App\Services\DeviceCommandService;
use App\Services\DevicePermissionService;
use App\Services\MqttPublisher;
use ReflectionMethod;
use Tests\TestCase;

class DeviceCommandPayloadTest extends TestCase
{
    public function test_optional_duration_omitted_when_absent(): void
    {
        $service = new DeviceCommandService(
            $this->createMock(MqttPublisher::class),
            $this->createMock(DevicePermissionService::class),
        );

        $method = new ReflectionMethod(DeviceCommandService::class, 'buildPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($service, [
            'type' => 'text',
            'content' => '{{text}}',
            'priority' => '{{priority}}',
            'duration' => '{{duration?}}',
        ], [
            'text' => 'Hello',
            'priority' => 'high',
        ]);

        $this->assertSame([
            'type' => 'text',
            'content' => 'Hello',
            'priority' => 'high',
        ], $payload);
    }

    public function test_boolean_payload_for_relay(): void
    {
        $service = new DeviceCommandService(
            $this->createMock(MqttPublisher::class),
            $this->createMock(DevicePermissionService::class),
        );

        $method = new ReflectionMethod(DeviceCommandService::class, 'buildPayload');
        $method->setAccessible(true);

        $payload = $method->invoke($service, [
            'state' => '{{on}}',
        ], [
            'on' => true,
        ]);

        $this->assertSame(['state' => true], $payload);
    }
}
