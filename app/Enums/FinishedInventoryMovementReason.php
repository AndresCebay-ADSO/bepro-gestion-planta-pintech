<?php

declare(strict_types=1);

namespace App\Enums;

enum FinishedInventoryMovementReason: string
{
    case Production = 'production';
    case Return = 'return';
    case Adjustment = 'adjustment';
    case Sale = 'sale';
    case Sample = 'sample';
    case Transfer = 'transfer';
    case Transformation = 'transformation';
    case Deterioration = 'deterioration';

    public function label(): string
    {
        return match ($this) {
            self::Production => __('Producción'),
            self::Return => __('Devolución'),
            self::Adjustment => __('Ajuste'),
            self::Sale => __('Venta'),
            self::Sample => __('Muestra'),
            self::Transfer => __('Traslado'),
            self::Transformation => __('Transformación'),
            self::Deterioration => __('Deterioro'),
        };
    }

    /**
     * Get valid reasons for a given movement type.
     *
     * @return list<self>
     */
    public static function forType(InventoryMovementType $type): array
    {
        return match ($type) {
            InventoryMovementType::Entry => [
                self::Production,
                self::Return,
                self::Adjustment,
            ],
            InventoryMovementType::Exit => [
                self::Sale,
                self::Sample,
                self::Transfer,
                self::Deterioration,
                self::Transformation,
                self::Adjustment,
            ],
        };
    }
}