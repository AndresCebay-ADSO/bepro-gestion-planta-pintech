<?php

namespace App\Services;

use App\Enums\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly RawMaterialReferencePriceService $rawMaterialReferencePriceService,
        private readonly ProductionCostRecalculationService $productionCostRecalculationService
    ) {}

    public function storeMovement(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $typeValue = $data['type'] instanceof InventoryMovementType ? $data['type']->value : $data['type'];
            $batchId = $this->resolveBatchIdForEntry($data, $typeValue);
            $isNewBatch = $typeValue === InventoryMovementType::Entry->value && ($data['batch_id'] ?? null) === null;

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

            if ($movementData['cost_price'] === null && $movementData['batch_id'] !== null) {
                $batchForCost = $this->getValidatedBatchForMovement(
                    rawMaterialId: (int) $movementData['raw_material_id'],
                    warehouseId: (int) $movementData['warehouse_id'],
                    batchId: (int) $movementData['batch_id'],
                );

                $movementData['cost_price'] = (float) $batchForCost->unit_price;
            }

            // Solo aplicar movimiento si no se creó un nuevo lote (el lote ya tiene la cantidad correcta)
            if (! $isNewBatch) {
                $this->applyMovement(
                    type: $movementData['type'],
                    quantity: $movementData['quantity'],
                    rawMaterialId: (int) $movementData['raw_material_id'],
                    warehouseId: (int) $movementData['warehouse_id'],
                    batchId: $movementData['batch_id'] !== null ? (int) $movementData['batch_id'] : null,
                    costPrice: $movementData['cost_price'] !== null ? (float) $movementData['cost_price'] : null,
                );
            }

            $movement = InventoryMovement::create($movementData);

            $this->syncReferencePriceAndDependentCosts((int) $movementData['raw_material_id']);

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

            $previousRawMaterialId = (int) $movement->raw_material_id;

            $this->reverseMovement($movement);
            $this->deleteBatchIfOrphaned($movement->batch_id);

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

            if ($movementData['cost_price'] === null && $movementData['batch_id'] !== null) {
                $batchForCost = $this->getValidatedBatchForMovement(
                    rawMaterialId: (int) $movementData['raw_material_id'],
                    warehouseId: (int) $movementData['warehouse_id'],
                    batchId: (int) $movementData['batch_id'],
                );

                $movementData['cost_price'] = (float) $batchForCost->unit_price;
            }

            $this->applyMovement(
                type: $movementData['type'],
                quantity: $movementData['quantity'],
                rawMaterialId: (int) $movementData['raw_material_id'],
                warehouseId: (int) $movementData['warehouse_id'],
                batchId: $movementData['batch_id'] !== null ? (int) $movementData['batch_id'] : null,
                costPrice: $movementData['cost_price'] !== null ? (float) $movementData['cost_price'] : null,
            );

            $movement->update($movementData);

            $updatedRawMaterialId = (int) $movementData['raw_material_id'];
            collect([$previousRawMaterialId, $updatedRawMaterialId])
                ->unique()
                ->each(fn (int $rawMaterialId) => $this->syncReferencePriceAndDependentCosts($rawMaterialId));

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

            $this->syncReferencePriceAndDependentCosts($rawMaterialId);
        });
    }

    private function syncReferencePriceAndDependentCosts(int $rawMaterialId): void
    {
        $currentPriceChanged = $this->rawMaterialReferencePriceService
            ->syncRawMaterialCurrentPrice($rawMaterialId);

        if ($currentPriceChanged) {
            $this->productionCostRecalculationService->recalculateForRawMaterial($rawMaterialId);
        }
    }

    private function applyMovement(
        string|InventoryMovementType $type,
        string|float $quantity,
        int $rawMaterialId,
        int $warehouseId,
        ?int $batchId,
        ?float $costPrice = null,
    ): void {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;

        if ($typeValue === InventoryMovementType::Exit->value && $batchId === null) {
            throw ValidationException::withMessages([
                'batch_id' => __('Debes seleccionar un lote para registrar una salida.'),
            ]);
        }

        if ($batchId === null) {
            return;
        }

        $batch = $this->getValidatedBatchForMovement($rawMaterialId, $warehouseId, $batchId);

        $quantity = (float) $quantity;

        if ($typeValue === InventoryMovementType::Entry->value) {
            $batch->initial_quantity = (float) $batch->initial_quantity + $quantity;
            $batch->remaining_quantity = (float) $batch->remaining_quantity + $quantity;

            if ($this->shouldSyncBatchUnitPrice($type, $quantity, $costPrice)) {
                $batch->unit_price = $costPrice;
            }

            $batch->save();

            return;
        }

        if ((float) $batch->remaining_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('La cantidad supera el stock disponible del lote seleccionado.'),
            ]);
        }

        $batch->remaining_quantity = (float) $batch->remaining_quantity - $quantity;
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
        $quantity = (float) $movement->quantity;

        if ($movement->type === InventoryMovementType::Entry) {
            if ((float) $batch->remaining_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'batch_id' => __('No es posible revertir el movimiento porque el lote no tiene stock suficiente.'),
                ]);
            }

            $batch->initial_quantity = (float) $batch->initial_quantity - $quantity;
            $batch->remaining_quantity = (float) $batch->remaining_quantity - $quantity;
            $batch->save();

            return;
        }

        $batch->remaining_quantity = (float) $batch->remaining_quantity + $quantity;
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

    private function resolveBatchIdForEntry(array $data, string $typeValue): ?int
    {
        $batchId = $data['batch_id'] ?? null;

        if ($typeValue !== InventoryMovementType::Entry->value || $batchId !== null) {
            return $batchId;
        }

        $batch = InventoryBatch::create([
            'raw_material_id' => $data['raw_material_id'],
            'warehouse_id' => $data['warehouse_id'],
            'initial_quantity' => $data['quantity'],
            'remaining_quantity' => $data['quantity'],
            'unit_price' => $data['cost_price'],
            'entry_date' => $data['movement_date'],
            'expiry_date' => null,
            'supplier' => null,
            'lot_number' => null,
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
            && (float) $batch->initial_quantity <= 0
            && (float) $batch->remaining_quantity <= 0
        ) {
            $batch->delete();
        }
    }

    private function shouldSyncBatchUnitPrice(string|InventoryMovementType $type, float $quantity, ?float $costPrice): bool
    {
        $typeValue = $type instanceof InventoryMovementType ? $type->value : $type;

        return $typeValue === InventoryMovementType::Entry->value
            && $quantity > 0
            && $costPrice !== null;
    }
}
