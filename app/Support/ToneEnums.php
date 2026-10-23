<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Enums\Contracts\HasTone;
use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\PaintDevelopmentRequestStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuotationStatus;
use App\Enums\RemnantStatus;
use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Enums\WarehouseType;

/**
 * Enums con tono que viajan al front en la prop compartida `enums` (HandleInertiaRequests), con su etiqueta y su tono
 * por caso. Es la única fuente del color de los estados: `<StatusBadge>` y `<PriorityBadge>` la leen.
 *
 * Al agregar un enum aquí, agregar su nombre corto a `ToneEnumName` en `resources/js/types/enums.ts`.
 * `ToneEnumsTest` falla si un enum con `HasTone` no está en la lista.
 */
final class ToneEnums
{
    /** @var list<class-string<HasTone&\BackedEnum>> */
    public const ALL = [
        AlertSeverity::class,
        AlertType::class,
        FinishedInventoryMovementReason::class,
        InventoryMovementType::class,
        PaintDevelopmentRequestStatus::class,
        ProductionOrderStatus::class,
        QuotationStatus::class,
        RemnantStatus::class,
        SalesOrderPriority::class,
        SalesOrderStatus::class,
        WarehouseType::class,
    ];

    /**
     * `{ SalesOrderStatus: { pending: { label: 'Pendiente', tone: 'warning' }, … }, … }`, indexado por el nombre corto
     * de la clase y el valor de cada caso.
     *
     * @return array<string, array<string, array{label: string, tone: string}>>
     */
    public static function shared(): array
    {
        $enums = [];

        foreach (self::ALL as $enum) {
            $cases = [];

            foreach ($enum::cases() as $case) {
                $cases[(string) $case->value] = [
                    'label' => $case->label(),
                    'tone' => $case->tone()->value,
                ];
            }

            $enums[class_basename($enum)] = $cases;
        }

        return $enums;
    }
}
