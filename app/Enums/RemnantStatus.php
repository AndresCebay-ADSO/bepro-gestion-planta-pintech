<?php

namespace App\Enums;

enum RemnantStatus: string
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
}