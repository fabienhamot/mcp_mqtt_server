<?php

namespace App\Services;

use App\Enums\DisplayAction;
use App\Enums\DisplayPriority;
use InvalidArgumentException;

/**
 * Payload MQTT strictement conforme au contrat Raspberry Pi :
 * {"type":"text"|"image"|"color"|"clear","content":"...","duration"?:int,"priority":"normal"|"high"}
 */
final class DisplayPayload
{
    private function __construct(
        public readonly DisplayAction $type,
        public readonly string $content,
        public readonly ?int $duration = null,
        public readonly DisplayPriority $priority = DisplayPriority::Normal,
    ) {
        if (! in_array($type, [DisplayAction::Text, DisplayAction::Image, DisplayAction::Color, DisplayAction::Clear], true)) {
            throw new InvalidArgumentException("Type MQTT non supporté : {$type->value}");
        }

        if ($duration !== null && $duration < 0) {
            throw new InvalidArgumentException('duration doit être un entier >= 0.');
        }
    }

    public static function text(
        string $text,
        ?int $duration = null,
        DisplayPriority $priority = DisplayPriority::Normal,
    ): self {
        $text = trim($text);

        if ($text === '') {
            throw new InvalidArgumentException('Le texte à afficher ne peut pas être vide.');
        }

        return new self(DisplayAction::Text, $text, $duration, $priority);
    }

    public static function image(
        string $imageUrl,
        ?int $duration = null,
        DisplayPriority $priority = DisplayPriority::Normal,
    ): self {
        if (! preg_match('#^https?://#i', $imageUrl)) {
            throw new InvalidArgumentException('image_url doit être une URL http(s).');
        }

        return new self(DisplayAction::Image, $imageUrl, $duration, $priority);
    }

    public static function color(string $color, ?int $duration = null): self
    {
        $color = trim($color);

        if (! self::isValidColor($color)) {
            throw new InvalidArgumentException(
                'color doit être un hex (#RRGGBB / #RGB) ou "r,g,b" (0-255).'
            );
        }

        return new self(DisplayAction::Color, $color, $duration, DisplayPriority::Normal);
    }

    public static function clear(): self
    {
        return new self(DisplayAction::Clear, '', null, DisplayPriority::Normal);
    }

    public static function isValidColor(string $color): bool
    {
        if (preg_match('/^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/', $color) === 1) {
            return true;
        }

        if (preg_match('/^(\d{1,3}),(\d{1,3}),(\d{1,3})$/', $color, $matches) === 1) {
            return (int) $matches[1] <= 255
                && (int) $matches[2] <= 255
                && (int) $matches[3] <= 255;
        }

        return false;
    }

    /**
     * @return array{type: string, content: string, priority: string, duration?: int}
     */
    public function toArray(): array
    {
        $payload = [
            'type' => $this->type->value,
            'content' => $this->content,
            'priority' => $this->priority->value,
        ];

        if ($this->duration !== null) {
            $payload['duration'] = $this->duration;
        }

        return $payload;
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
