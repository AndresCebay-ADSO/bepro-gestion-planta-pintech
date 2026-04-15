<?php

namespace App\Enums;

enum ComponentSystem: string
{
    case OneK = '1K';
    case TwoK = '2K';
    case Kit = 'KIT';

    public function label(): string
    {
        return match ($this) {
            self::OneK => '1K',
            self::TwoK => '2K',
            self::Kit => 'Kit',
        };
    }
}
