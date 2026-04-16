<?php

namespace App\Enums;

enum PriceUpdateType: string
{
    case Manual = 'manual';
    case Automatico = 'automatico';

    public function label(): string
    {
        return match ($this) {
            self::Manual => __('Manual'),
            self::Automatico => __('Automático'),
        };
    }
}
