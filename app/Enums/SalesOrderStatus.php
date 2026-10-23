<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Contracts\HasTone;

enum SalesOrderStatus: string implements HasTone
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

    public function tone(): Tone
    {
        return match ($this) {
            self::Pending => Tone::Warning,
            self::InProgress => Tone::Info,
            self::Ready => Tone::Success,
            self::Delivered => Tone::Success,
            self::Cancelled => Tone::Danger,
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
}
