<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\FinishedInventory;
use App\Services\FinishedInventoryQueryService;
use App\Services\WarehouseContextService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinishedInventoryController extends Controller
{
    public function __construct(
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
        private readonly WarehouseContextService $warehouseContextService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', FinishedInventory::class);

        $user = $request->user();
        $search = strtolower(trim((string) $request->input('search')));
        $warehouseId = $request->integer('warehouse_id') ?: null;
        $productId = $request->integer('product_id') ?: null;

        $query = $this->finishedInventoryQueryService
            ->scopedQuery($user)
            ->where('quantity', '>', 0)
            ->with([
                'product:id,code,name,category_id',
                'product.category:id,name',
                'productVariant:id,code,name,presentation_label,presentation_value,unit_of_measure_id',
                'productVariant.unitOfMeasure:id,symbol',
                'warehouse:id,name,city,type',
            ])
            ->latest('id');

        if ($warehouseId !== null) {
            $accessibleIds = $this->finishedInventoryQueryService->accessibleWarehouseIds($user);
            if ($user->hasRole('admin') || in_array($warehouseId, $accessibleIds, true)) {
                $query->where('warehouse_id', $warehouseId);
            }
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
                    'unit_symbol' => $row->productVariant->unitOfMeasure?->symbol,
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

        return Inertia::render('Inventory/FinishedInventory/Index', [
            'inventory' => $inventory,
            'warehouses' => $warehouses,
            'filters' => [
                'search' => $search,
                'warehouse_id' => $warehouseId,
                'product_id' => $productId,
            ],
        ]);
    }
}
