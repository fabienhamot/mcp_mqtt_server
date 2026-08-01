<?php

namespace Tests\Unit;

use App\Enums\DisplayPriority;
use App\Services\DisplayPayload;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DisplayPayloadTest extends TestCase
{
    #[Test]
    public function it_builds_strict_text_payload(): void
    {
        $payload = DisplayPayload::text('Hello', 10, DisplayPriority::High);

        $this->assertSame([
            'type' => 'text',
            'content' => 'Hello',
            'priority' => 'high',
            'duration' => 10,
        ], $payload->toArray());
    }

    #[Test]
    public function it_rejects_non_http_image_url(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DisplayPayload::image('ftp://example.com/a.png');
    }

    #[Test]
    public function it_accepts_hex_and_rgb_colors(): void
    {
        $this->assertSame('#ff0000', DisplayPayload::color('#ff0000')->toArray()['content']);
        $this->assertSame('255,0,128', DisplayPayload::color('255,0,128')->toArray()['content']);
    }

    #[Test]
    public function clear_payload_ignores_content(): void
    {
        $payload = DisplayPayload::clear()->toArray();

        $this->assertSame('clear', $payload['type']);
        $this->assertSame('', $payload['content']);
        $this->assertArrayNotHasKey('duration', $payload);
    }
}
