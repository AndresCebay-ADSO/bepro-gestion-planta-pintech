<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesOrderPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    public function label(): string
    {
        return match ($this) {
            self::Low => __('Baja'),
            self::Medium => __('Media'),
            self::High => __('Alta'),
        };
    }

    public function order(): int
    {
        return match ($this) {
            self::Low => 0,
            self::Medium => 1,
            self::High => 2,
        };
    }
}
