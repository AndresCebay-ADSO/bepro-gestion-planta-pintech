<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\RemnantConsumption;
use App\Services\DecimalCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumeRemnantAction
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Consume parcial o totalmente un saldo de PT en una orden de producción activa.
     */
    public function execute(
        ProductionRemnant $remnant,
        ProductionOrder $targetOrder,
        string $quantityGallons,
        int $userId,
        ?string $notes = null
    ): RemnantConsumption {
        return DB::transaction(function () use ($remnant, $targetOrder, $quantityGallons, $userId, $notes): RemnantConsumption {
            $lockedRemnant = ProductionRemnant::query()
                ->lockForUpdate()
                ->findOrFail($remnant->id);

            $lockedOrder = ProductionOrder::query()
                ->lockForUpdate()
                ->findOrFail($targetOrder->id);

            $this->validate($lockedRemnant, $lockedOrder, $quantityGallons);

            $quantityKg = $this->calculator->mul($quantityGallons, (string) $lockedRemnant->density_kg_per_gallon, 4);

            $newAvailableGallons = $this->calculator->sub(
                (string) $lockedRemnant->available_quantity_gallons,
                $quantityGallons,
                4
            );
            $newAvailableKg = $this->calculator->sub(
                (string) $lockedRemnant->available_quantity_kg,
                $quantityKg,
                4
            );

            $newStatus = $this->calculator->cmp($newAvailableGallons, '0', 4) <= 0
                ? RemnantStatus::Consumed
                : RemnantStatus::PartiallyConsumed;

            $lockedRemnant->update([
                'available_quantity_gallons' => $newAvailableGallons,
                'available_quantity_kg' => $newAvailableKg,
                'status' => $newStatus,
            ]);

            return RemnantConsumption::create([
                'remnant_id' => $lockedRemnant->id,
                'target_order_id' => $lockedOrder->id,
                'quantity_gallons' => $quantityGallons,
                'quantity_kg' => $quantityKg,
                'consumed_by' => $userId,
                'consumed_at' => now(),
                'notes' => $notes,
            ]);
        }, attempts: 3);
    }

    private function validate(ProductionRemnant $remnant, ProductionOrder $targetOrder, string $quantityGallons): void
    {
        if (! in_array($remnant->status, [RemnantStatus::Available, RemnantStatus::PartiallyConsumed], true)) {
            throw ValidationException::withMessages([
                'remnant_id' => __('Este saldo ya fue consumido completamente.'),
            ]);
        }

        if ($targetOrder->status !== ProductionOrderStatus::InProgress) {
            throw ValidationException::withMessages([
                'target_order_id' => __('Solo se puede consumir saldo en órdenes en progreso.'),
            ]);
        }

        if ((int) $remnant->warehouse_id !== (int) $targetOrder->warehouse_id) {
            throw ValidationException::withMessages([
                'remnant_id' => __('El saldo debe estar en la misma bodega que la orden destino.'),
            ]);
        }

        if ($this->calculator->cmp($quantityGallons, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity_gallons' => __('La cantidad a consumir debe ser mayor a cero.'),
            ]);
        }

        if ($this->calculator->cmp($quantityGallons, (string) $remnant->available_quantity_gallons, 4) > 0) {
            throw ValidationException::withMessages([
                'quantity_gallons' => __('No hay suficiente saldo disponible. Disponible: :available gal.', [
                    'available' => $remnant->available_quantity_gallons,
                ]),
            ]);
        }
    }
}
