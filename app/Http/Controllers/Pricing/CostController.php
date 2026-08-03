<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\UpdateCostRequest;
use App\Models\Product;
use App\Services\VariantSalesPriceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostController extends Controller
{
    public function __construct(
        private readonly VariantSalesPriceService $salesPriceService,
    ) {}

    /**
     * Display the costs dashboard for admin.
     */
    public function index(Request $request): Response
    {
        $search = $request->input('search');

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
                        ->orWhere('code', 'like', "%{$search}%");
                });
            })
            ->active()
            ->orderBy('name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Costs/Index', [
            'products' => $products,
            'can' => [
                'update_margin' => true,
            ],
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    /**
     * Update the sales margin for a product.
     */
    public function update(UpdateCostRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();

        $salesMargin = $validated['sales_margin'] ?? null;

        if (isset($validated['sales_price']) && $validated['sales_price'] !== null) {
            if (empty($product->current_price) || $product->current_price <= 0) {
                return back()->withErrors([
                    'sales_price' => 'No se puede calcular el margen porque el producto no tiene precio interno.',
                ]);
            }

            $salesMargin = (float) $this->salesPriceService->resolveMarginFromSalesPrice(
                $product->current_price,
                $validated['sales_price'],
            );
        }

        if ($salesMargin !== null && ($salesMargin < 0 || $salesMargin >= 100)) {
            return back()->withErrors([
                'sales_price' => 'El precio ingresado genera un margen inválido (debe estar entre 0% y 99.99%).',
            ]);
        }

        $product->update([
            'sales_margin' => $salesMargin,
        ]);

        return back()->with('success', 'Margen de venta actualizado correctamente.');
    }
}
