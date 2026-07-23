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
        $warehouseIds = $this->accessibleWarehouseIds($user);

        if ($warehouseIds === []) {
            return FinishedInventory::query()->whereRaw('1 = 0');
        }

        return FinishedInventory::query()->whereIn('warehouse_id', $warehouseIds);
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

        if ($variantIds !== null) {
            if ($variantIds === []) {
                return collect();
            }

            $query->whereIn('product_variant_id', $variantIds);
        }

        return $query->pluck('total_quantity', 'product_variant_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function buildIndexData(User $user, ?string $search, ?int $warehouseId, ?int $productId): array
    {
        $search = $search !== null ? strtolower(trim($search)) : '';

        $query = $this->scopedQuery($user)
            ->where('quantity', '>', 0)
            ->with([
                'product:id,code,name,category_id',
                'product.category:id,name',
                'productVariant:id,code,name,presentation_label,presentation_value',
                'warehouse:id,name,city,type',
            ])
            ->latest('id');

        if ($warehouseId !== null) {
            $query->where('warehouse_id', $warehouseId);
        }

        if ($productId !== null) {
            $query->where('product_id', $productId);
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->whereHas('product', function ($productQuery) use ($search): void {
                        $productQuery
                            ->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('productVariant', function ($variantQuery) use ($search): void {
                        $variantQuery
                            ->whereRaw('LOWER(code) LIKE ?', ["%{$search}%"])
                            ->orWhereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    })
                    ->orWhereHas('warehouse', function ($warehouseQuery) use ($search): void {
                        $warehouseQuery->whereRaw('LOWER(name) LIKE ?', ["%{$search}%"]);
                    });
            });
        }

        $inventory = $query
            ->paginate(20)
            ->onEachSide(1)
            ->withQueryString()
            ->through(fn (FinishedInventory $row): array => [
                'id' => $row->id,
                'quantity' => $row->quantity,
                'product' => $row->product ? [
                    'id' => $row->product->id,
                    'code' => $row->product->code,
                    'name' => $row->product->name,
                    'category' => $row->product->category ? [
                        'name' => $row->product->category->name,
                    ] : null,
                ] : null,
                'variant' => $row->productVariant ? [
                    'id' => $row->productVariant->id,
                    'code' => $row->productVariant->code,
                    'name' => $row->productVariant->name,
                    'presentation_label' => $row->productVariant->presentation_label,
                    'presentation_value' => $row->productVariant->presentation_value,
                ] : null,
                'warehouse' => $row->warehouse ? [
                    'id' => $row->warehouse->id,
                    'name' => $row->warehouse->name,
                    'city' => $row->warehouse->city,
                    'type' => $row->warehouse->type->value,
                ] : null,
            ]);

        $warehouses = $this->warehouseContextService
            ->availableWarehouses($user)
            ->map(fn ($warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
                'city' => $warehouse->city,
            ])
            ->values();

        return [
            'inventory' => $inventory,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
                'product_name' => $productId !== null ? Product::query()->whereKey($productId)->value('name') : null,
            ],
        ];
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
