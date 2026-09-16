<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Filters\PriceListFilter;
use App\Http\Requests\Pricing\IndexPriceListRequest;
use App\Models\Product;
use App\Models\User;

class PriceListService
{
    public function __construct(
        private readonly VariantSalesPriceService $salesPriceService,
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function buildList(User $user, IndexPriceListRequest $request): array
    {
        $canViewCosts = $user?->can(Permission::CostsView->value) ?? false;

        $baseQuery = Product::query()
            ->select([
                'id',
                'code',
                'name',
                'current_cost',
                'cif_percentage',
                'current_price',
                'sales_margin',
            ])
            ->active()
            ->with([
                'variants' => function ($query): void {
                    $query->select([
                        'id',
                        'product_id',
                        'code',
                        'name',
                        'presentation_label',
                        'presentation_value',
                        'current_price',
                    ])
                        ->where('is_active', true)
                        ->orderBy('presentation_value', 'asc');
                },
            ]);

        $products = (new PriceListFilter($request))
            ->apply($baseQuery)
            ->orderBy('name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        $variantIds = $products->getCollection()
            ->flatMap(fn (Product $p) => $p->variants->pluck('id'))
            ->all();

        $variantStockTotals = $user !== null
            ? $this->finishedInventoryQueryService->sumQuantityByVariant($user, $variantIds)
            : collect();

        $products->through(function (Product $product) use ($canViewCosts, $variantStockTotals) {
            $resolvedProductPrice = $this->salesPriceService->resolveForProduct($product);
            $productSalesPrice = $resolvedProductPrice !== null ? (float) $resolvedProductPrice : null;

            $productData = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'sales_price' => $productSalesPrice,
            ];

            // Con el precio de venta y el margen se despeja el precio interno: el margen también es costo.
            if ($canViewCosts) {
                $productData = array_merge($productData, [
                    'sales_margin' => $product->sales_margin,
                    'current_cost' => $product->current_cost,
                    'cif_percentage' => $product->cif_percentage,
                    'current_price' => $product->current_price,
                ]);
            }

            $variants = $product->variants->map(function ($variant) use ($canViewCosts, $variantStockTotals) {
                $resolvedVariantPrice = $this->salesPriceService->resolveForVariant($variant);
                $variantSalesPrice = $resolvedVariantPrice !== null ? (float) $resolvedVariantPrice : null;
                $availableStock = $variantStockTotals->get($variant->id);

                $variantData = [
                    'id' => $variant->id,
                    'code' => $variant->code,
                    'name' => $variant->name,
                    'presentation_label' => $variant->presentation_label,
                    'presentation_value' => $variant->presentation_value,
                    'sales_price' => $variantSalesPrice,
                    'available_stock' => $availableStock !== null ? (float) $availableStock : 0.0,
                ];

                if ($canViewCosts) {
                    $variantData = array_merge($variantData, [
                        'current_price' => $variant->current_price,
                    ]);
                }

                return $variantData;
            });

            $productData['variants'] = $variants;

            return $productData;
        });

        return [
            'products' => $products,
            'can' => [
                'view_costs' => $canViewCosts,
                'view_prices' => true,
            ],
            'filters' => $request->validated(),
        ];
    }
}
