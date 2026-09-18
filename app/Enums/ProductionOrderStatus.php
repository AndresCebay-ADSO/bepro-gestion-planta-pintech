<?php

namespace App\Enums;

enum ProductionOrderStatus: string
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
}
