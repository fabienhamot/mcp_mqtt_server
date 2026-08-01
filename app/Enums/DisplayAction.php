<?php

namespace App\Enums;

enum DisplayAction: string
{
    case Text = 'text';
    case Image = 'image';
    case Color = 'color';
    case Clear = 'clear';
    case Status = 'status';
    case List = 'list';

    /**
     * @return list<string>
     */
    public static function controllableValues(): array
    {
        return [
            self::Text->value,
            self::Image->value,
            self::Color->value,
            self::Clear->value,
        ];
    }
}
