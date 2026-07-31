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

            if ($reason === FinishedInventoryMovementReason::Return) {
                $returnable = $this->calculateReturnableForBatchWarehouse($batchId, $warehouseId);

                if ($this->calculator->cmp($quantity, $returnable) > 0) {
                    throw ValidationException::withMessages([
                        'quantity' => __('La cantidad de devolución no puede superar lo que realmente salió del lote. Retornable máximo: :returnable', ['returnable' => $returnable]),
                    ]);
                }
            }

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

    private function calculateReturnableForBatchWarehouse(int $batchId, int $warehouseId): string
    {
        $exitReasons = [
            FinishedInventoryMovementReason::Sale->value,
            FinishedInventoryMovementReason::Sample->value,
            FinishedInventoryMovementReason::Deterioration->value,
            FinishedInventoryMovementReason::Transformation->value,
        ];

        $eligibleExits = (string) FinishedInventoryMovement::query()
            ->where('finished_product_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', InventoryMovementType::Exit)
            ->whereIn('reason', $exitReasons)
            ->sum('quantity');

        $totalReturns = (string) FinishedInventoryMovement::query()
            ->where('finished_product_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->where('type', InventoryMovementType::Entry)
            ->where('reason', FinishedInventoryMovementReason::Return->value)
            ->sum('quantity');

        $returnable = $this->calculator->sub($eligibleExits, $totalReturns);

        return $this->calculator->cmp($returnable, '0') < 0 ? '0' : $returnable;
    }
}
