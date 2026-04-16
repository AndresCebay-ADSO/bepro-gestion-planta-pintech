<?php

namespace App\Enums;

enum AlertSeverity: string
{
    case Baja = 'baja';
    case Media = 'media';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::Baja => __('Baja'),
            self::Media => __('Media'),
            self::Alta => __('Alta'),
        };
    }
}
