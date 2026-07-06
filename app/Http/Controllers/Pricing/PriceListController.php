<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\DecimalCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PriceListController extends Controller
{
    public function __construct(
        private readonly DecimalCalculator $calculator
    ) {}

    /**
     * Display the price list for admin and comercial roles.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $search = $request->input('search');
        $isAdmin = $user?->hasRole('admin') ?? false;

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
        $products->through(function (Product $product) use ($isAdmin) {
            $salesMargin = $product->sales_margin ?? 0;
            $productSalesPrice = $this->calculateSalesPrice(
                $product->current_price,
                $salesMargin
            );

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

            $variants = $product->variants->map(function ($variant) use ($salesMargin, $isAdmin) {
                $variantSalesPrice = $this->calculateSalesPrice(
                    $variant->current_price,
                    $salesMargin
                );

                $variantData = [
                    'id' => $variant->id,
                    'code' => $variant->code,
                    'name' => $variant->name,
                    'presentation_label' => $variant->presentation_label,
                    'presentation_value' => $variant->presentation_value,
                    'sales_price' => $variantSalesPrice,
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

    /**
     * Calculate sales price from current price and margin using decimal arithmetic.
     */
    private function calculateSalesPrice(?float $currentPrice, float $salesMargin): ?float
    {
        if ($currentPrice === null) {
            return null;
        }

        $marginDecimal = $this->calculator->div((string) $salesMargin, '100', 4);
        $multiplier = $this->calculator->add('1', $marginDecimal, 4);
        $result = $this->calculator->mul((string) $currentPrice, $multiplier, 4);

        return (float) $result;
    }
}
