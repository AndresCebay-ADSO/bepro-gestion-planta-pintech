<?php

declare(strict_types=1);

namespace App\Services\FinishedInventory;

use App\Exceptions\InsufficientStockException;
use App\Models\FinishedProductBatch;
use App\Models\FinishedProductBatchStock;
use App\Services\DecimalCalculator;
use Illuminate\Database\Eloquent\Collection;

/**
 * Low-level service for atomic stock manipulation on the batch_stocks pivot table.
 *
 * All write operations use pessimistic locking (`lockForUpdate`) and
 * string-based arithmetic via DecimalCalculator to guarantee precision.
 */
class FinishedProductBatchStockService
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
    ) {}

    /**
     * Increment the stock of a batch in a specific warehouse.
     * Creates the pivot row if it doesn't exist yet.
     */
    public function incrementStock(int $batchId, int $warehouseId, string $quantity): FinishedProductBatchStock
    {
        if ($this->calculator->cmp($quantity, '0') <= 0) {
            throw new \DomainException('La cantidad para operaciones de stock debe ser estrictamente mayor a 0.');
        }

        $stock = FinishedProductBatchStock::query()
            ->lockForUpdate()
            ->createOrFirst(
                ['finished_product_batch_id' => $batchId, 'warehouse_id' => $warehouseId],
                ['quantity' => 0]
            );

        $stock->quantity = $this->calculator->add((string) $stock->quantity, $quantity);
        $stock->save();

        return $stock;
    }

    /**
     * Decrement the stock of a batch in a specific warehouse.
     *
     * @throws InsufficientStockException when requested quantity exceeds available stock
     */
    public function decrementStock(int $batchId, int $warehouseId, string $quantity): FinishedProductBatchStock
    {
        if ($this->calculator->cmp($quantity, '0') <= 0) {
            throw new \DomainException('La cantidad para operaciones de stock debe ser estrictamente mayor a 0.');
        }

        $stock = FinishedProductBatchStock::query()
            ->lockForUpdate()
            ->where('finished_product_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->first();

        if ($stock === null) {
            throw InsufficientStockException::insufficientQuantity('0', $quantity);
        }

        $available = (string) $stock->quantity;

        if ($this->calculator->cmp($available, $quantity) < 0) {
            throw InsufficientStockException::insufficientQuantity($available, $quantity);
        }

        /** @var FinishedProductBatchStock $stock */
        $stock->quantity = $this->calculator->sub((string) $stock->quantity, $quantity);
        $stock->save();

        return $stock;
    }

    /**
     * Get the available stock of a batch in a specific warehouse.
     */
    public function getAvailableStock(int $batchId, int $warehouseId): string
    {
        $quantity = FinishedProductBatchStock::query()
            ->where('finished_product_batch_id', $batchId)
            ->where('warehouse_id', $warehouseId)
            ->value('quantity');

        return (string) ($quantity ?? '0');
    }

    /**
     * Get available batches for a product variant in a warehouse, ordered FIFO.
     *
     * @return Collection<int, FinishedProductBatch>
     */
    public function availableBatchesForWarehouse(int $productVariantId, int $warehouseId): Collection
    {
        return FinishedProductBatch::query()
            ->where('product_variant_id', $productVariantId)
            ->whereHas('stocks', fn ($q) => $q->forWarehouse($warehouseId)->available())
            ->with(['stocks' => fn ($q) => $q->forWarehouse($warehouseId)])
            ->fifoOrder()
            ->get();
    }
}
