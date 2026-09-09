<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductVariantRequest;
use App\Http\Requests\Products\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductionCostRecalculationService $productionCostRecalculationService
    ) {}

    public function store(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $validated['product_id'] = $product->id;

        DB::transaction(function () use ($validated, $product): void {
            ProductVariant::create($validated);
            $this->productionCostRecalculationService->recalculateForProduct((int) $product->id);
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante creada exitosamente.'));
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if((int) $variant->product_id !== (int) $product->id, 404);

        $validated = $request->validated();

        DB::transaction(function () use ($variant, $validated, $product): void {
            $variant->update($validated);
            $this->productionCostRecalculationService->recalculateForProduct((int) $product->id);
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante actualizada exitosamente.'));
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if((int) $variant->product_id !== (int) $product->id, 404);

        DB::transaction(function () use ($variant): void {
            $variant->delete();
        });

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante eliminada exitosamente.'));
    }
}
