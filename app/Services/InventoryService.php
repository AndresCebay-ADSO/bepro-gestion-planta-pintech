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
        private readonly DecimalCalculator $calculator,
        private readonly AlertService $alertService,
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

            $rawMaterialId = (int) $movementData['raw_material_id'];
            $this->dispatchReferencePriceAndDependentCosts($rawMaterialId);
            $this->evaluateAlertsAfterMovement($rawMaterialId, $batchId);

            return $movement;
        });
    }

    private function evaluateAlertsAfterMovement(int $rawMaterialId, int|string|null $batchId): void
    {
        $this->alertService->evaluateLowStock($rawMaterialId);

        if ($batchId !== null && $batchId !== '') {
            $this->alertService->evaluateBatchExpiry((int) $batchId);
        }
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

    // TODO: [Deuda arquitectónica] Extraer resolveBatchIdForEntry() y getValidatedBatchForMovement()
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

    private function shouldSyncBatchUnitPrice(string|InventoryMovementType $type, string $quantity, ?string $costPrice): bool
    {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;

        return $typeValue === InventoryMovementType::Entry->value
            && $this->calculator->isPositive($quantity)
            && $costPrice !== null;
    }
}
