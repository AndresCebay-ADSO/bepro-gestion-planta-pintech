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
     * Razones que una persona puede elegir al registrar un movimiento a mano. «Producción» no está: solo la crea
     * `CompleteProductionOrderAction` al completar una orden, con su lote y su costo. «Traslado» tampoco: lo registra el
     * servicio de traslado, que crea la salida y la entrada juntas. Una entrada «Producción» a mano inflaba el
     * inventario con apariencia de producción real (auditoría 3.6, PT-02).
     *
     * Las listas de `entryReasons` y `exitReasons` del front (`types/finished-inventory.ts`) son copia de estas; un test
     * las compara.
     *
     * @return list<self>
     */
    public static function manualReasonsFor(InventoryMovementType $type): array
    {
        return match ($type) {
            InventoryMovementType::Entry => [
                self::Return,
                self::Adjustment,
            ],
            InventoryMovementType::Exit => [
                self::Sale,
                self::Sample,
                self::Deterioration,
                self::Transformation,
                self::Adjustment,
            ],
        };
    }
}
