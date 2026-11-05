<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\RemnantStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\RemnantConsumption;
use App\Models\User;
use App\Services\DecimalCalculator;
use App\Services\Production\ProductionOrderLock;
use App\Support\NumberFormat;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConsumeRemnantAction
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
        private readonly ProductionOrderLock $orderLock,
    ) {}

    /**
     * Consume parcial o totalmente un saldo de PT en una orden de producción activa. Los kilos mandan: los galones
     * disponibles se recalculan de los kilos que quedan y los consumidos salen de la resta, así que todos los consumos
     * de un saldo suman exactamente sus galones originales y el costo se traslada completo.
     */
    public function execute(
        ProductionRemnant $remnant,
        ProductionOrder $targetOrder,
        string $quantityKg,
        User $user,
        ?string $notes = null
    ): RemnantConsumption {
        return DB::transaction(function () use ($remnant, $targetOrder, $quantityKg, $user, $notes): RemnantConsumption {
            $lockedRemnant = ProductionRemnant::query()
                ->lockForUpdate()
                ->findOrFail($remnant->id);

            // Siempre saldo y después OP: nadie bloquea en el orden contrario, así que no hay abrazo mortal.
            $lockedOrder = $this->orderLock->lockFor(
                $targetOrder,
                $user,
                'consumeRemnant',
                "No se puede consumir un saldo en una orden en estado ':status'.",
            );

            $this->validate($lockedRemnant, $lockedOrder, $quantityKg);

            $availableKg = (string) $lockedRemnant->available_quantity_kg;
            $newAvailableKg = $this->calculator->sub($availableKg, $quantityKg, 4);
            $newAvailableGallons = $this->calculator->div($newAvailableKg, (string) $lockedRemnant->density_kg_per_gallon, 4);

            // Un resto de menos de 0,0001 gal no se puede usar: el saldo queda consumido con todos los kilos que había.
            $exhausted = $this->calculator->isZero($newAvailableGallons);

            if ($exhausted) {
                $quantityKg = $availableKg;
                $newAvailableKg = '0';
            }

            $consumedGallons = $this->calculator->sub((string) $lockedRemnant->available_quantity_gallons, $newAvailableGallons, 4);

            if (! $this->calculator->isPositive($consumedGallons)) {
                throw ValidationException::withMessages([
                    'quantity_kg' => __('La cantidad es demasiado pequeña para registrarla.'),
                ]);
            }

            $lockedRemnant->update([
                'available_quantity_gallons' => $newAvailableGallons,
                'available_quantity_kg' => $newAvailableKg,
                'status' => $exhausted ? RemnantStatus::Consumed : RemnantStatus::PartiallyConsumed,
            ]);

            $consumedCost = null;
            if ($lockedRemnant->cost_per_gallon !== null) {
                $consumedCost = $this->calculator->mul(
                    $consumedGallons,
                    (string) $lockedRemnant->cost_per_gallon,
                    4
                );
            }

            return RemnantConsumption::create([
                'remnant_id' => $lockedRemnant->id,
                'target_order_id' => $lockedOrder->id,
                'quantity_gallons' => $consumedGallons,
                'quantity_kg' => $quantityKg,
                'consumed_cost' => $consumedCost,
                'consumed_by' => $user->id,
                'consumed_at' => now(),
                'notes' => $notes,
            ]);
        }, attempts: 3);
    }

    private function validate(ProductionRemnant $remnant, ProductionOrder $targetOrder, string $quantityKg): void
    {
        if (! in_array($remnant->status, [RemnantStatus::Available, RemnantStatus::PartiallyConsumed], true)) {
            throw ValidationException::withMessages([
                'remnant_id' => __('Este saldo ya fue consumido completamente.'),
            ]);
        }

        if ((int) $remnant->warehouse_id !== (int) $targetOrder->warehouse_id) {
            throw ValidationException::withMessages([
                'remnant_id' => __('El saldo debe estar en la misma bodega que la orden destino.'),
            ]);
        }

        if ($this->calculator->cmp($quantityKg, '0', 4) <= 0) {
            throw ValidationException::withMessages([
                'quantity_kg' => __('La cantidad a consumir debe ser mayor a cero.'),
            ]);
        }

        if ($this->calculator->cmp($quantityKg, (string) $remnant->available_quantity_kg, 4) > 0) {
            throw ValidationException::withMessages([
                'quantity_kg' => __('No hay suficiente saldo disponible. Disponible: :available kg.', [
                    'available' => NumberFormat::quantity($remnant->available_quantity_kg),
                ]),
            ]);
        }
    }
}
