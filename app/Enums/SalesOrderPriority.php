<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum SalesOrderPriority: string implements HasTone
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

    /**
     * Tono de la pastilla (DESIGN.md §4). Solo la prioridad alta lleva color; baja y media se distinguen por las barras
     * del icono (`<PriorityBadge>`).
     */
    public function tone(): Tone
    {
        return match ($this) {
            self::Low => Tone::Neutral,
            self::Medium => Tone::Neutral,
            self::High => Tone::Danger,
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
