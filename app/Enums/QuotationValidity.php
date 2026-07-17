<?php

declare(strict_types=1);

namespace App\Enums;

enum QuotationValidity: int
{
    case ThirtyDays = 30;
    case FortyFiveDays = 45;
    case SixtyDays = 60;

    public function label(): string
    {
        return match ($this) {
            self::ThirtyDays => __('30 días'),
            self::FortyFiveDays => __('45 días'),
            self::SixtyDays => __('60 días'),
        };
    }
}
