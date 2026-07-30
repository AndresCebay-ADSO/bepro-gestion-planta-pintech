<?php

declare(strict_types=1);

namespace App\Services\FinishedInventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\FinishedInventory;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Services\DecimalCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * High-level orchestrator for finished product inventory movements.
 *
 * This is the ONLY entry point for modifying finished product stock.
 * Every operation:
 * 1. Updates batch_stocks (source of truth) via FinishedProductBatchStockService
 * 2. Creates a movement record (audit trail)
 * 3. Syncs the finished_inventories cache table
 *
 * All operations are wrapped in DB::transaction.
 */
class FinishedInventoryMovementService
{
    public function __construct(
        private readonly FinishedProductBatchStockService $stockService,
        private readonly DecimalCalculator $calculator,
    ) {}

    /**
     * Register a stock entry (production, return, adjustment, transfer-in).
     */
    public function registerEntry(
        int $batchId,
        int $warehouseId,
        string $quantity,
        FinishedInventoryMovementReason $reason,
        int $userId,
        ?int $productionOrderId = null,
        ?string $costPrice = null,
        ?string $notes = null,
        ?\DateTimeInterface $movementDate = null,
    ): FinishedInventoryMovement {
        return DB::transaction(function () use ($batchId, $warehouseId, $quantity, $reason, $userId, $productionOrderId, $costPrice, $notes, $movementDate) {
            $batch = $this->lockBatch($batchId);

            $this->stockService->incrementStock($batchId, $warehouseId, $quantity);

            $movement = FinishedInventoryMovement::create([
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'warehouse_id' => $warehouseId,
                'production_order_id' => $productionOrderId,
                'finished_product_batch_id' => $batchId,
                'type' => InventoryMovementType::Entry,
                'reason' => $reason,
                'quantity' => $quantity,
                'cost_price' => $costPrice,
                'movement_date' => $movementDate ?? now(),
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->syncInventoryCache(
                (int) $batch->product_id,
                $batch->product_variant_id,
                $warehouseId,
                $quantity,
                isEntry: true,
            );

            return $movement;
        });
    }

    /**
     * Register a stock exit (sale, sample, deterioration, adjustment, transfer-out).
     *
     * @throws InsufficientStockException when stock is insufficient
     */
    public function registerExit(
        int $batchId,
        int $warehouseId,
        string $quantity,
        FinishedInventoryMovementReason $reason,
        int $userId,
        ?string $notes = null,
        ?\DateTimeInterface $movementDate = null,
    ): FinishedInventoryMovement {
        return DB::transaction(function () use ($batchId, $warehouseId, $quantity, $reason, $userId, $notes, $movementDate) {
            $batch = $this->lockBatch($batchId);

            $this->stockService->decrementStock($batchId, $warehouseId, $quantity);

            $movement = FinishedInventoryMovement::create([
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'warehouse_id' => $warehouseId,
                'finished_product_batch_id' => $batchId,
                'type' => InventoryMovementType::Exit,
                'reason' => $reason,
                'quantity' => $quantity,
                'movement_date' => $movementDate ?? now(),
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            $this->syncInventoryCache(
                (int) $batch->product_id,
                $batch->product_variant_id,
                $warehouseId,
                $quantity,
                isEntry: false,
            );

            return $movement;
        });
    }

    /**
     * Transfer stock between warehouses.
     *
     * Creates two movements (exit from source, entry to destination)
     * within a single transaction. The batch identity is preserved.
     */
    public function transfer(
        int $batchId,
        int $fromWarehouseId,
        int $toWarehouseId,
        string $quantity,
        int $userId,
        ?string $notes = null,
        ?\DateTimeInterface $movementDate = null,
    ): void {
        if ($fromWarehouseId === $toWarehouseId) {
            throw ValidationException::withMessages([
                'destination_warehouse_id' => __('La bodega destino debe ser diferente a la bodega origen.'),
            ]);
        }

        DB::transaction(function () use ($batchId, $fromWarehouseId, $toWarehouseId, $quantity, $userId, $notes, $movementDate) {
            $this->registerExit(
                batchId: $batchId,
                warehouseId: $fromWarehouseId,
                quantity: $quantity,
                reason: FinishedInventoryMovementReason::Transfer,
                userId: $userId,
                notes: $notes,
                movementDate: $movementDate,
            );

            $this->registerEntry(
                batchId: $batchId,
                warehouseId: $toWarehouseId,
                quantity: $quantity,
                reason: FinishedInventoryMovementReason::Transfer,
                userId: $userId,
                notes: $notes,
                movementDate: $movementDate,
            );
        });
    }

    /**
     * Reverse a movement by creating an inverse movement (for audit trail).
     *
     * Production-linked movements cannot be reversed.
     */
    public function reverseMovement(FinishedInventoryMovement $movement): FinishedInventoryMovement
    {
        $this->rejectProductionLinkedModification($movement, 'revertir');

        $this->requireBatchId($movement);

        $notes = __('Reversión del movimiento #:id', ['id' => $movement->id]);

        if ($movement->type === InventoryMovementType::Entry) {
            return $this->registerExit(
                batchId: (int) $movement->finished_product_batch_id,
                warehouseId: (int) $movement->warehouse_id,
                quantity: (string) $movement->quantity,
                reason: FinishedInventoryMovementReason::Adjustment,
                userId: (int) $movement->created_by,
                notes: $notes,
            );
        }

        return $this->registerEntry(
            batchId: (int) $movement->finished_product_batch_id,
            warehouseId: (int) $movement->warehouse_id,
            quantity: (string) $movement->quantity,
            reason: FinishedInventoryMovementReason::Adjustment,
            userId: (int) $movement->created_by,
            notes: $notes,
        );
    }

    /**
     * Update a movement. Metadata-only changes (date, notes) skip stock recalculation.
     * Substantial changes reverse the old effect and apply the new one.
     */
    public function updateMovement(FinishedInventoryMovement $movement, array $data): FinishedInventoryMovement
    {
        $this->rejectProductionLinkedModification($movement, 'editar');

        return DB::transaction(function () use ($movement, $data) {
            if ($this->isMetadataOnlyUpdate($movement, $data)) {
                $movement->update([
                    'movement_date' => $data['movement_date'],
                    'notes' => array_key_exists('notes', $data) ? $data['notes'] : $movement->notes,
                ]);

                return $movement->refresh();
            }

            $this->requireBatchId($movement);

            // Reverse old stock effect (no new movement record)
            $this->reverseStockEffect($movement);

            // Apply new values
            $batch = $this->lockBatch((int) $data['finished_product_batch_id']);
            $type = $data['type'] instanceof InventoryMovementType
                ? $data['type']
                : InventoryMovementType::from($data['type']);
            $reason = $data['reason'] instanceof FinishedInventoryMovementReason
                ? $data['reason']
                : FinishedInventoryMovementReason::from($data['reason']);
            $quantity = (string) $data['quantity'];
            $warehouseId = (int) $data['warehouse_id'];

            if ($type === InventoryMovementType::Entry) {
                $this->stockService->incrementStock((int) $batch->id, $warehouseId, $quantity);
                $this->syncInventoryCache((int) $batch->product_id, $batch->product_variant_id, $warehouseId, $quantity, isEntry: true);
            } else {
                $this->stockService->decrementStock((int) $batch->id, $warehouseId, $quantity);
                $this->syncInventoryCache((int) $batch->product_id, $batch->product_variant_id, $warehouseId, $quantity, isEntry: false);
            }

            $movement->update([
                'product_id' => $batch->product_id,
                'product_variant_id' => $batch->product_variant_id,
                'warehouse_id' => $warehouseId,
                'finished_product_batch_id' => (int) $batch->id,
                'type' => $type,
                'reason' => $reason,
                'quantity' => $quantity,
                'movement_date' => $data['movement_date'],
                'notes' => array_key_exists('notes', $data) ? $data['notes'] : $movement->notes,
            ]);

            return $movement->refresh();
        });
    }

    /**
     * Delete a movement. Reverses the stock effect and removes the record.
     *
     * Production-linked movements cannot be deleted.
     */
    public function deleteMovement(FinishedInventoryMovement $movement): void
    {
        $this->rejectProductionLinkedModification($movement, 'eliminar');

        DB::transaction(function () use ($movement) {
            if ($movement->finished_product_batch_id !== null) {
                $this->reverseStockEffect($movement);
            }

            $movement->delete();
        });
    }

    /**
     * Reverse the stock effect of a movement without creating a new movement record.
     * Used internally by updateMovement() and deleteMovement().
     */
    private function reverseStockEffect(FinishedInventoryMovement $movement): void
    {
        $batch = $this->lockBatch((int) $movement->finished_product_batch_id);
        $quantity = (string) $movement->quantity;
        $warehouseId = (int) $movement->warehouse_id;

        if ($movement->type === InventoryMovementType::Entry) {
            $this->stockService->decrementStock((int) $movement->finished_product_batch_id, $warehouseId, $quantity);
            $this->syncInventoryCache((int) $batch->product_id, $batch->product_variant_id, $warehouseId, $quantity, isEntry: false);
        } else {
            $this->stockService->incrementStock((int) $movement->finished_product_batch_id, $warehouseId, $quantity);
            $this->syncInventoryCache((int) $batch->product_id, $batch->product_variant_id, $warehouseId, $quantity, isEntry: true);
        }
    }

    /**
     * Sync the finished_inventories cache table after a stock change.
     *
     * This table aggregates total quantity per (product, variant, warehouse)
     * and is used by the existing finished inventory views.
     */
    private function syncInventoryCache(
        int $productId,
        ?int $productVariantId,
        int $warehouseId,
        string $quantity,
        bool $isEntry,
    ): void {
        $inventory = FinishedInventory::query()
            ->lockForUpdate()
            ->createOrFirst(
                [
                    'product_id' => $productId,
                    'product_variant_id' => $productVariantId,
                    'warehouse_id' => $warehouseId,
                ],
                ['quantity' => 0]
            );

        $currentQty = (string) ($inventory->quantity ?? '0');
        $inventory->quantity = $isEntry
            ? $this->calculator->add($currentQty, $quantity)
            : $this->calculator->sub($currentQty, $quantity);
        $inventory->save();
    }

    private function lockBatch(int $batchId): FinishedProductBatch
    {
        return FinishedProductBatch::query()->lockForUpdate()->findOrFail($batchId);
    }

    private function rejectProductionLinkedModification(FinishedInventoryMovement $movement, string $action): void
    {
        if ($movement->production_order_id !== null) {
            throw ValidationException::withMessages([
                'movement' => __('No se permite :action movimientos vinculados a órdenes de producción.', ['action' => $action]),
            ]);
        }
    }

    private function requireBatchId(FinishedInventoryMovement $movement): void
    {
        if ($movement->finished_product_batch_id === null) {
            throw ValidationException::withMessages([
                'movement' => __('Este movimiento no tiene lote asociado y no puede ser modificado.'),
            ]);
        }
    }

    private function isMetadataOnlyUpdate(FinishedInventoryMovement $movement, array $data): bool
    {
        $typeValue = $data['type'] instanceof InventoryMovementType ? $data['type']->value : $data['type'];
        $reasonValue = $data['reason'] instanceof FinishedInventoryMovementReason ? $data['reason']->value : $data['reason'];

        return (int) ($movement->finished_product_batch_id ?? 0) === (int) ($data['finished_product_batch_id'] ?? 0)
            && (int) $movement->warehouse_id === (int) $data['warehouse_id']
            && $movement->type->value === $typeValue
            && $movement->reason->value === $reasonValue
            && $this->calculator->cmp((string) $movement->quantity, (string) $data['quantity']) === 0;
    }
}
