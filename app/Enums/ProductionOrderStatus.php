<?php

namespace App\Enums;

enum ProductionOrderStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case PendingReview = 'pending_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

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
