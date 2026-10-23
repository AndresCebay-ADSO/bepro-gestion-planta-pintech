<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum AlertSeverity: string implements HasTone
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

    public function tone(): Tone
    {
        return match ($this) {
            self::Baja => Tone::Neutral,
            self::Media => Tone::Warning,
            self::Alta => Tone::Danger,
        };
    }
}
