<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use App\Services\DecimalCalculator;
use Illuminate\Database\Eloquent\Collection;

class InventoryBatchService
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * @return Collection<int, InventoryBatch>
     */
    public function availableForRawMaterial(int $rawMaterialId, int $warehouseId, bool $lockForUpdate = false): Collection
    {
        $query = InventoryBatch::query()
            ->where('raw_material_id', $rawMaterialId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get();
    }

    /**
     * @param  array<int, int>  $rawMaterialIds
     * @param  array<int, string>  $columns
     * @return Collection<int, InventoryBatch>
     */
    public function availableForMaterials(int $warehouseId, array $rawMaterialIds, array $columns = ['*']): Collection
    {
        if ($rawMaterialIds === []) {
            return new Collection;
        }

        return InventoryBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('raw_material_id', $rawMaterialIds)
            ->where('remaining_quantity', '>', 0)
            ->orderBy('entry_date')
            ->orderBy('id')
            ->get($columns);
    }

    /**
     * @param  array<int, int>  $rawMaterialIds
     * @return array<int, string>
     */
    public function availableQuantitiesByMaterial(int $warehouseId, array $rawMaterialIds): array
    {
        if ($rawMaterialIds === []) {
            return [];
        }

        return InventoryBatch::query()
            ->where('warehouse_id', $warehouseId)
            ->whereIn('raw_material_id', $rawMaterialIds)
            ->selectRaw('raw_material_id, COALESCE(SUM(remaining_quantity), 0) as available_quantity')
            ->groupBy('raw_material_id')
            ->pluck('available_quantity', 'raw_material_id')
            ->map(fn (mixed $availableQuantity): string => $this->calculator->round((string) $availableQuantity, 4))
            ->all();
    }

    public function decrementRemainingQuantity(InventoryBatch $batch, float|string $quantity): void
    {
        $quantityStr = (string) $quantity;

        if (! $this->calculator->isPositive($quantityStr)) {
            return;
        }

        $batch->update([
            'remaining_quantity' => $this->calculator->sub($batch->remaining_quantity, $quantityStr),
        ]);
    }
}
