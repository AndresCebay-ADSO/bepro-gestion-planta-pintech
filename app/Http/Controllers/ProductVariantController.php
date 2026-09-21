<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Http\Requests\Products\StoreProductVariantRequest;
use App\Http\Requests\Products\UpdateProductVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\DeactivationGuardService;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ProductionCostRecalculationService $productionCostRecalculationService,
        private readonly DeactivationGuardService $deactivationGuard,
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    public function store(StoreProductVariantRequest $request, Product $product): RedirectResponse
    {
        $this->authorize('manageVariants', $product);

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
        $this->authorize('manageVariants', $product);

        $validated = $request->validated();

        $deactivating = array_key_exists('is_active', $validated) && ! $validated['is_active'] && $variant->is_active;

        $blocker = DB::transaction(function () use ($variant, $validated, $product, $deactivating): ?string {
            if ($deactivating) {
                $blocker = $this->deactivationGuard->variantBlocker(ProductVariant::query()->lockForUpdate()->findOrFail($variant->id));

                if ($blocker !== null) {
                    return $blocker;
                }
            }

            $variant->update($validated);
            $this->productionCostRecalculationService->recalculateForProduct((int) $product->id);

            return null;
        });

        if ($blocker !== null) {
            return back()->with('error', $blocker);
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante actualizada exitosamente.'));
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        $this->authorize('manageVariants', $product);

        // Solo si nunca se usó (cotizaciones, pedidos, inventario, lotes, precios): si no, se desactiva
        // (docs/POLITICA_ELIMINACION.md §3.1).
        if (! $this->deleteUnused->execute($variant)) {
            return back()->with('error', __('La presentación tiene historial (cotizaciones, pedidos, inventario o precios). Desactívala en su lugar.'));
        }

        return redirect()
            ->route('products.show', $product)
            ->with('success', __('Variante eliminada exitosamente.'));
    }
}
