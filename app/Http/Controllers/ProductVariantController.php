<?php

namespace App\Http\Controllers;

use App\Http\Requests\Products\StoreProductVariantRequest;
use App\Http\Requests\Products\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;

class ProductVariantController extends Controller
{
    public function store(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('update', $product);

        $validated = $request->validated();
        $validated['product_id'] = $product->id;

        ProductVariant::create($validated);

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante creada exitosamente.'));
    }

    public function update(UpdateProductVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if((int) $variant->product_id !== (int) $product->id, 404);

        $variant->update($request->validated());

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante actualizada exitosamente.'));
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('update', $product);

        abort_if((int) $variant->product_id !== (int) $product->id, 404);

        $variant->delete();

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante eliminada exitosamente.'));
    }
}
