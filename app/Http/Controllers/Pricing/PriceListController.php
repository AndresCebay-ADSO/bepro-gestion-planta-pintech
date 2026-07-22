<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\FinishedInventoryQueryService;
use App\Services\VariantSalesPriceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(
        private readonly VariantSalesPriceService $salesPriceService,
        private readonly FinishedInventoryQueryService $finishedInventoryQueryService,
    ) {}

    /**
     * Display the price list for admin and comercial roles.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->input('search');
        $isAdmin = $user?->hasRole('admin') ?? false;

        $variantStockTotals = $user !== null
            ? $this->finishedInventoryQueryService->sumQuantityByVariant($user)
            : collect();

        $products = Product::query()
            ->select([
                'id',
                'code',
                'name',
                'current_cost',
                'cif_percentage',
                'current_price',
                'sales_margin',
            ])
            ->when($search, function ($query) use ($search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhereHas('variants', function ($vq) use ($search): void {
                            $vq->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                });
            })
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
            ])
            ->orderBy('name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        // Transform variants to include sales_price
        $products->through(function (Product $product) use ($isAdmin, $variantStockTotals) {
            $resolvedProductPrice = $this->salesPriceService->resolveForProduct($product);
            $productSalesPrice = $resolvedProductPrice !== null ? (float) $resolvedProductPrice : null;

            $productData = [
                'id' => $product->id,
                'code' => $product->code,
                'name' => $product->name,
                'sales_margin' => $product->sales_margin,
                'sales_price' => $productSalesPrice,
            ];

            if ($isAdmin) {
                $productData = array_merge($productData, [
                    'current_cost' => $product->current_cost,
                    'cif_percentage' => $product->cif_percentage,
                    'current_price' => $product->current_price,
                ]);
            }

            $variants = $product->variants->map(function ($variant) use ($isAdmin, $variantStockTotals) {
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

                if ($isAdmin) {
                    $variantData = array_merge($variantData, [
                        'current_price' => $variant->current_price,
                    ]);
                }

                return $variantData;
            });

            $productData['variants'] = $variants;

            return $productData;
        });

        return Inertia::render('Prices/Index', [
            'products' => $products,
            'can' => [
                'view_costs' => $isAdmin,
                'view_prices' => true,
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }
}
