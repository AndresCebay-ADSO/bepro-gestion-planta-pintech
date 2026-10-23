<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum ProductionOrderStatus: string implements HasTone
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Estados de una orden en curso: bloquean desactivar su producto o su bodega
     * (docs/POLITICA_ELIMINACION.md §3.1).
     *
     * @return array<int, self>
     */
    public static function open(): array
    {
        return [self::Pending, self::InProgress, self::PendingReview];
    }

    /**
     * Estados desde los que se puede cancelar una orden: aún no se entregó a revisión ni se completó. Los usa la acción
     * y el botón «Cancelar orden» del detalle (D-OPX2). Una orden en revisión se devuelve a planta antes.
     *
     * @return array<int, self>
     */
    public static function cancellable(): array
    {
        return [self::Pending, self::InProgress];
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendiente'),
            self::InProgress => __('En progreso'),
            self::PendingReview => __('Pendiente de revisión'),
            self::Completed => __('Completada'),
            self::Cancelled => __('Cancelada'),
        };
    }

    public function tone(): Tone
    {
        return match ($this) {
            self::Pending => Tone::Warning,
            self::InProgress => Tone::Info,
            self::PendingReview => Tone::Warning,
            self::Completed => Tone::Success,
            self::Cancelled => Tone::Danger,
        };
    }
}
