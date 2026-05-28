<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Jobs\RecalculateRawMaterialReferencePrice;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    public function storeMovement(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $typeValue = $data['type'] instanceof InventoryMovementType ? $data['type']->value : $data['type'];
            $this->rejectManualProductionOrderLink($data['production_order_id'] ?? null);

            $batchId = $this->resolveBatchIdForEntry($data, $typeValue);

            $movementData = [
                'raw_material_id' => $data['raw_material_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch_id' => $batchId,
                'production_order_id' => $data['production_order_id'] ?? null,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'cost_price' => $data['cost_price'] ?? null,
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ];

            // Obtener y bloquear el lote una sola vez para resolver precio y aplicar movimiento
            $lockedBatch = $batchId !== null
                ? $this->getValidatedBatchForMovement(
                    (int) $movementData['raw_material_id'],
                    (int) $movementData['warehouse_id'],
                    (int) $batchId
                )
                : null;

            $movementData['cost_price'] = $this->resolveMovementCostPrice(
                typeValue: $typeValue,
                requestedCostPrice: $movementData['cost_price'],
                lockedBatch: $lockedBatch,
            );

            $this->applyMovement(
                type: $movementData['type'],
                quantity: $movementData['quantity'],
                costPrice: $movementData['cost_price'],
                lockedBatch: $lockedBatch,
            );

            $movement = InventoryMovement::create($movementData);

            $this->dispatchReferencePriceAndDependentCosts((int) $movementData['raw_material_id']);

            return $movement;
        });
    }

    public function updateMovement(InventoryMovement $movement, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $data) {
            if ($movement->production_order_id !== null) {
                throw ValidationException::withMessages([
                    'movement' => __('No se permite editar movimientos vinculados a órdenes de producción.'),
                ]);
            }

            if ($this->isMetadataOnlyUpdate($movement, $data)) {
                $movement->update([
                    'movement_date' => $data['movement_date'],
                    'notes' => $data['notes'] ?? null,
                ]);
                $this->syncBatchEntryDateForSoleEntryMovement($movement);

                // No se despacha recálculo: notas y fecha no afectan precio de referencia.
                // TODO: Si la política "last_lot" estuviera activa y la fecha cambiara,
                //       podría necesitarse un dispatch condicional aquí.
                return $movement->refresh();
            }

            $this->rejectManualProductionOrderLink($data['production_order_id'] ?? null);

            $previousRawMaterialId = (int) $movement->raw_material_id;
            $previousBatchId = $movement->batch_id;

            $this->reverseMovement($movement);

            $typeValue = $data['type'] instanceof InventoryMovementType ? $data['type']->value : $data['type'];
            $batchId = $this->resolveBatchIdForEntry($data, $typeValue);

            $movementData = [
                'raw_material_id' => $data['raw_material_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch_id' => $batchId,
                'production_order_id' => $data['production_order_id'] ?? null,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'cost_price' => $data['cost_price'] ?? null,
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'] ?? null,
            ];

            $lockedBatch = $batchId !== null
                ? $this->getValidatedBatchForMovement(
                    (int) $movementData['raw_material_id'],
                    (int) $movementData['warehouse_id'],
                    (int) $batchId
                )
                : null;

            $movementData['cost_price'] = $this->resolveMovementCostPrice(
                typeValue: $typeValue,
                requestedCostPrice: $movementData['cost_price'],
                lockedBatch: $lockedBatch,
            );

            $this->applyMovement(
                type: $movementData['type'],
                quantity: $movementData['quantity'],
                costPrice: $movementData['cost_price'],
                lockedBatch: $lockedBatch,
            );

            $movement->update($movementData);
            $this->syncBatchEntryDateForSoleEntryMovement($movement);
            $this->deleteBatchIfOrphaned($previousBatchId);

            $updatedRawMaterialId = (int) $movementData['raw_material_id'];
            collect([$previousRawMaterialId, $updatedRawMaterialId])
                ->unique()
                ->each(fn (int $rawMaterialId) => $this->dispatchReferencePriceAndDependentCosts($rawMaterialId));

            return $movement->refresh();
        });
    }

    public function deleteMovement(InventoryMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            if ($movement->production_order_id !== null) {
                throw ValidationException::withMessages([
                    'movement' => __('No se permite eliminar movimientos vinculados a órdenes de producción.'),
                ]);
            }

            $rawMaterialId = (int) $movement->raw_material_id;

            $this->reverseMovement($movement);
            $movement->delete();
            $this->deleteBatchIfOrphaned($movement->batch_id);

            $this->dispatchReferencePriceAndDependentCosts($rawMaterialId);
        });
    }

    private function dispatchReferencePriceAndDependentCosts(int $rawMaterialId): void
    {
        RecalculateRawMaterialReferencePrice::dispatch($rawMaterialId)->afterCommit();
    }

    private function applyMovement(
        string|InventoryMovementType $type,
        string|float $quantity,
        ?string $costPrice = null,
        ?InventoryBatch $lockedBatch = null,
    ): void {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;

        if ($typeValue === InventoryMovementType::Exit->value && $lockedBatch === null) {
            throw ValidationException::withMessages([
                'batch_id' => __('Debes seleccionar un lote para registrar una salida.'),
            ]);
        }

        if ($lockedBatch === null) {
            return;
        }

        $batch = $lockedBatch;
        $quantity = (string) $quantity;

        if ($typeValue === InventoryMovementType::Entry->value) {
            $previousInitialQuantity = (string) $batch->initial_quantity;
            $previousRemainingQuantity = (string) $batch->remaining_quantity;

            $batch->initial_quantity = $this->calculator->add($batch->initial_quantity, $quantity);
            $batch->remaining_quantity = $this->calculator->add($batch->remaining_quantity, $quantity);

            if ($this->shouldSyncBatchUnitPrice($type, $quantity, $costPrice)) {
                // Regla: un lote mantiene un solo costo; si el costo cambia, debe crearse otro lote.
                if (
                    ($this->calculator->isPositive($previousInitialQuantity) || $this->calculator->isPositive($previousRemainingQuantity))
                    && ! $this->decimalValuesAreEqual($batch->unit_price, $costPrice)
                ) {
                    throw ValidationException::withMessages([
                        'cost_price' => __('El costo no coincide con el lote seleccionado. Crea un lote nuevo para registrar esta entrada.'),
                    ]);
                }

                $batch->unit_price = $costPrice;
            }

            $batch->save();

            return;
        }

        if ($this->calculator->cmp($batch->remaining_quantity, $quantity) < 0) {
            throw ValidationException::withMessages([
                'quantity' => __('La cantidad supera el stock disponible del lote seleccionado.'),
            ]);
        }

        $batch->remaining_quantity = $this->calculator->sub($batch->remaining_quantity, $quantity);
        $batch->save();
    }

    private function reverseMovement(InventoryMovement $movement): void
    {
        if ($movement->batch_id === null) {
            return;
        }

        $batch = $this->getValidatedBatchForMovement(
            rawMaterialId: (int) $movement->raw_material_id,
            warehouseId: (int) $movement->warehouse_id,
            batchId: (int) $movement->batch_id
        );
        $quantity = (string) $movement->quantity;

        if ($movement->type === InventoryMovementType::Entry) {
            if ($this->calculator->cmp($batch->remaining_quantity, $quantity) < 0) {
                throw ValidationException::withMessages([
                    'batch_id' => __('No es posible revertir el movimiento porque el lote no tiene stock suficiente.'),
                ]);
            }

            $batch->initial_quantity = $this->calculator->sub($batch->initial_quantity, $quantity);
            $batch->remaining_quantity = $this->calculator->sub($batch->remaining_quantity, $quantity);
            $batch->save();

            return;
        }

        $batch->remaining_quantity = $this->calculator->add($batch->remaining_quantity, $quantity);
        $batch->save();
    }

    private function getValidatedBatchForMovement(int $rawMaterialId, int $warehouseId, int $batchId): InventoryBatch
    {
        $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($batchId);

        if ((int) $batch->raw_material_id !== $rawMaterialId) {
            throw ValidationException::withMessages([
                'batch_id' => __('El lote seleccionado no pertenece a la materia prima indicada.'),
            ]);
        }

        if ((int) $batch->warehouse_id !== $warehouseId) {
            throw ValidationException::withMessages([
                'batch_id' => __('El lote seleccionado no pertenece a la bodega indicada.'),
            ]);
        }

        return $batch;
    }

    private function rejectManualProductionOrderLink(int|string|null $productionOrderId): void
    {
        if ($productionOrderId === null || $productionOrderId === '') {
            return;
        }

        throw ValidationException::withMessages([
            'production_order_id' => __('Los movimientos manuales no pueden asociarse a una orden de producción.'),
        ]);
    }

    private function resolveMovementCostPrice(
        string $typeValue,
        float|int|string|null $requestedCostPrice,
        ?InventoryBatch $lockedBatch = null,
    ): ?string {
        if ($lockedBatch === null) {
            return $requestedCostPrice !== null ? (string) $requestedCostPrice : null;
        }

        if ($typeValue === InventoryMovementType::Exit->value || $requestedCostPrice === null) {
            return (string) $lockedBatch->unit_price;
        }

        return (string) $requestedCostPrice;
    }

    private function syncBatchEntryDateForSoleEntryMovement(InventoryMovement $movement): void
    {
        if ($movement->type !== InventoryMovementType::Entry || $movement->batch_id === null) {
            return;
        }

        $batch = InventoryBatch::query()
            ->lockForUpdate()
            ->find($movement->batch_id);

        if ($batch === null) {
            return;
        }

        $hasOtherMovements = $batch->inventoryMovements()
            ->whereKeyNot($movement->id)
            ->exists();

        if ($hasOtherMovements) {
            return;
        }

        $batch->update(['entry_date' => $movement->movement_date]);
    }

    private function isMetadataOnlyUpdate(InventoryMovement $movement, array $data): bool
    {
        return (int) $movement->raw_material_id === (int) $data['raw_material_id']
            && (int) $movement->warehouse_id === (int) $data['warehouse_id']
            && $this->nullableIntegersAreEqual($movement->batch_id, $data['batch_id'] ?? null)
            && $this->movementTypesAreEqual($movement->type, $data['type'])
            && $this->decimalValuesAreEqual($movement->quantity, $data['quantity'])
            && $this->decimalValuesAreEqual($movement->cost_price, $data['cost_price'] ?? null)
            && $this->nullableIntegersAreEqual($movement->production_order_id, $data['production_order_id'] ?? null);
    }

    private function nullableIntegersAreEqual(int|string|null $valueA, int|string|null $valueB): bool
    {
        if ($valueA === null || $valueA === '') {
            return $valueB === null || $valueB === '';
        }

        if ($valueB === null || $valueB === '') {
            return false;
        }

        return (int) $valueA === (int) $valueB;
    }

    private function movementTypesAreEqual(string|InventoryMovementType $typeA, string|InventoryMovementType $typeB): bool
    {
        $typeAValue = $typeA instanceof InventoryMovementType ? $typeA->value : $typeA;
        $typeBValue = $typeB instanceof InventoryMovementType ? $typeB->value : $typeB;

        return $typeAValue === $typeBValue;
    }

    private function decimalValuesAreEqual(float|int|string|null $valueA, float|int|string|null $valueB): bool
    {
        if ($valueA === null || $valueA === '') {
            return $valueB === null || $valueB === '';
        }

        if ($valueB === null || $valueB === '') {
            return false;
        }

        return $this->calculator->cmp($valueA, $valueB) === 0;
    }

    // TODO: [Deuda arquitectónica] Extraer resolveBatchIdForEntry(), deleteBatchIfOrphaned(),
    //       syncBatchEntryDateForSoleEntryMovement() y getValidatedBatchForMovement()
    //       a un InventoryBatchService dedicado. InventoryService debería ser orquestador.
    private function resolveBatchIdForEntry(array $data, string $typeValue): ?int
    {
        $batchId = $data['batch_id'] ?? null;

        if ($typeValue !== InventoryMovementType::Entry->value || $batchId !== null) {
            return $batchId;
        }

        $lotNumber = $data['lot_number'] ?? null;
        if ($lotNumber === null || trim((string) $lotNumber) === '') {
            throw ValidationException::withMessages([
                'lot_number' => __('Debes indicar el número de lote para crear un lote nuevo.'),
            ]);
        }

        $batch = InventoryBatch::create([
            'raw_material_id' => $data['raw_material_id'],
            'warehouse_id' => $data['warehouse_id'],
            'initial_quantity' => 0,
            'remaining_quantity' => 0,
            'unit_price' => $data['cost_price'],
            'entry_date' => $data['movement_date'],
            'expiry_date' => ($data['expiry_date'] ?? null) ?: null,
            'supplier' => ($data['supplier'] ?? null) ?: null,
            'lot_number' => ($data['lot_number'] ?? null) ?: null,
        ]);

        return (int) $batch->id;
    }

    private function deleteBatchIfOrphaned(int|string|null $batchId): void
    {
        if ($batchId === null) {
            return;
        }

        $batch = InventoryBatch::query()->find((int) $batchId);
        if ($batch === null) {
            return;
        }

        $hasMovements = $batch->inventoryMovements()->exists();

        if (
            ! $hasMovements
            && ! $this->calculator->isPositive($batch->initial_quantity)
            && ! $this->calculator->isPositive($batch->remaining_quantity)
        ) {
            $batch->delete();
        }
    }

    private function shouldSyncBatchUnitPrice(string|InventoryMovementType $type, string $quantity, ?string $costPrice): bool
    {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;

        return $typeValue === InventoryMovementType::Entry->value
            && $this->calculator->isPositive($quantity)
            && $costPrice !== null;
    }
}
