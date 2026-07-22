<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FinishedInventory;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class FinishedInventoryQueryService
{
    public function __construct(
        private readonly WarehouseContextService $warehouseContextService,
    ) {}

    /**
     * @return list<int>
     */
    public function accessibleWarehouseIds(User $user): array
    {
        return $this->warehouseContextService
            ->availableWarehouses($user)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    public function scopedQuery(User $user): Builder
    {
        $query = FinishedInventory::query();

        if ($user->hasRole('admin')) {
            return $query;
        }

        $warehouseIds = $this->accessibleWarehouseIds($user);

        if ($warehouseIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('warehouse_id', $warehouseIds);
    }

    /**
     * @param  list<int>|null  $variantIds
     * @return Collection<int, string|float>
     */
    public function sumQuantityByVariant(User $user, ?array $variantIds = null): Collection
    {
        $query = $this->scopedQuery($user)
            ->selectRaw('product_variant_id, SUM(quantity) as total_quantity')
            ->whereNotNull('product_variant_id')
            ->groupBy('product_variant_id');

        if ($variantIds !== null && $variantIds !== []) {
            $query->whereIn('product_variant_id', $variantIds);
        }

        return $query->pluck('total_quantity', 'product_variant_id');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function inventoryRowsForProduct(User $user, Product $product): array
    {
        return $this->scopedQuery($user)
            ->where('product_id', $product->id)
            ->where('quantity', '>', 0)
            ->with([
                'warehouse:id,name,city,type',
                'productVariant:id,code,name,presentation_label,presentation_value',
                'productVariant.unitOfMeasure:id,symbol',
            ])
            ->orderBy('warehouse_id')
            ->orderBy('product_variant_id')
            ->get()
            ->map(fn (FinishedInventory $row): array => [
                'id' => $row->id,
                'quantity' => $row->quantity,
                'warehouse' => $row->warehouse ? [
                    'id' => $row->warehouse->id,
                    'name' => $row->warehouse->name,
                    'city' => $row->warehouse->city,
                    'type' => $row->warehouse->type->value,
                ] : null,
                'variant' => $row->productVariant ? [
                    'id' => $row->productVariant->id,
                    'code' => $row->productVariant->code,
                    'name' => $row->productVariant->name,
                    'presentation_label' => $row->productVariant->presentation_label,
                    'presentation_value' => $row->productVariant->presentation_value,
                    'unit_symbol' => $row->productVariant->unitOfMeasure?->symbol,
                ] : null,
            ])
            ->values()
            ->all();
    }
}
