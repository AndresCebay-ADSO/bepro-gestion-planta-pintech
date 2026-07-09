<?php

declare(strict_types=1);

namespace App\Enums;

enum SalesOrderStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('Pendiente'),
            self::InProgress => __('En progreso'),
            self::Ready => __('Lista'),
            self::Delivered => __('Entregada'),
            self::Cancelled => __('Cancelada'),
        };
    }

    /**
     * Devuelve los estados a los que se puede transicionar desde el estado actual.
     *
     * @return array<int, self>
     */
    public function nextTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Ready, self::Cancelled],
            self::Ready => [self::Delivered, self::Cancelled],
            self::Delivered => [],
            self::Cancelled => [],
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::InProgress => 'blue',
            self::Ready => 'green',
            self::Delivered => 'purple',
            self::Cancelled => 'gray',
        };
    }
}
