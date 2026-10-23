<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum RemnantStatus: string implements HasTone
{
    case Available = 'available';
    case PartiallyConsumed = 'partially_consumed';
    case Consumed = 'consumed';

    public function label(): string
    {
        return match ($this) {
            self::Available => __('Disponible'),
            self::PartiallyConsumed => __('Parcialmente consumido'),
            self::Consumed => __('Consumido'),
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Available => Tone::Success,
            self::PartiallyConsumed => Tone::Warning,
            self::Consumed => Tone::Neutral,
        };
    }
}
