<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\InventoryBatch;
use Illuminate\Database\Eloquent\Collection;

class InventoryBatchService
{
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
     * @return array<int, float>
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
            ->map(fn (mixed $availableQuantity): float => (float) $availableQuantity)
            ->all();
    }

    public function decrementRemainingQuantity(InventoryBatch $batch, float $quantity): void
    {
        if ($quantity <= 0) {
            return;
        }

        $batch->decrement('remaining_quantity', $quantity);
    }
}
