<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pricing;

use App\Enums\Permission;
use App\Filters\CostFilter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Pricing\IndexCostRequest;
use App\Http\Requests\Pricing\UpdateCostRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CostController extends Controller
{
    /**
     * Display the costs dashboard for admin.
     */
    public function index(IndexCostRequest $request): Response
    {
        $products = (new CostFilter($request))
            ->apply(Product::query())
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
            ->orderBy('name')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString();

        return Inertia::render('Costs/Index', [
            'products' => $products,
            'can' => [
                'update_margin' => $request->user()?->can(Permission::CostsUpdate->value) ?? false,
            ],
            'filters' => $request->validated(),
        ]);
    }

    /**
     * Guarda el margen de venta. UpdateCostRequest autoriza, valida y lo calcula (bcmath).
     */
    public function update(UpdateCostRequest $request, Product $product): RedirectResponse
    {
        $product->update(['sales_margin' => $request->salesMargin()]);

        return back()->with('success', 'Margen de venta actualizado correctamente.');
    }
}
