<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StorePackagingPlanRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Http\RedirectResponse;

class PackagingPlanController extends Controller
{
    /**
     * Agregar un plan de envasado a una orden de producción.
     */
    public function store(StorePackagingPlanRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        ProductionOrderPackagingPlan::create([
            'production_order_id' => $productionOrder->id,
            'product_variant_id' => $validated['product_variant_id'],
            'planned_units' => $validated['planned_units'],
        ]);

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Plan de envasado agregado.');
    }

    /**
     * Eliminar un plan de envasado (solo si la orden no está cerrada).
     */
    public function destroy(ProductionOrder $productionOrder, ProductionOrderPackagingPlan $plan): RedirectResponse
    {
        $this->authorize('updateOperationalData', $productionOrder);

        if ((int) $plan->production_order_id !== $productionOrder->id) {
            abort(404);
        }

        $plan->delete();

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Plan de envasado eliminado.');
    }
}
