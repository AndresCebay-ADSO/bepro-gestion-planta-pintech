<?php

namespace App\Http\Controllers\Pricing;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\DecimalCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CostController extends Controller
{
    public function __construct(
        private readonly DecimalCalculator $calculator,
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
    public function update(Request $request, Product $product): RedirectResponse
    {
        $validated = $request->validate([
            'sales_margin' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'sales_price' => ['nullable', 'numeric', 'gt:0'],
        ]);

        $salesMargin = $validated['sales_margin'] ?? null;

        if (isset($validated['sales_price']) && $validated['sales_price'] !== null) {
            if (empty($product->current_price) || $product->current_price <= 0) {
                return back()->withErrors([
                    'sales_price' => 'No se puede calcular el margen porque el producto no tiene precio interno.',
                ]);
            }

            $ratio = $this->calculator->div(
                (string) $product->current_price,
                (string) $validated['sales_price'],
                6,
            );
            $marginDecimal = $this->calculator->sub('1', $ratio, 6);
            $salesMargin = (float) $this->calculator->mul($marginDecimal, '100', 2);
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
