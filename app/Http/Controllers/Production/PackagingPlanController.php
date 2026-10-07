<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StorePackagingPlanRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackagingPlanController extends Controller
{
    /**
     * Agregar un plan de envasado a una orden de producción.
     */
    public function store(StorePackagingPlanRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // En su propia transacción: si choca, PostgreSQL revierte solo esta inserción y la conexión sigue usable.
            DB::transaction(fn () => ProductionOrderPackagingPlan::createForVariant((int) $productionOrder->id, (int) $validated['product_variant_id'], $validated['planned_units']));
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones a la vez con la misma presentación pasan las dos la validación; la segunda choca con el
            // índice único y recibe el mismo mensaje que la validación, no un error 500.
            throw ValidationException::withMessages([
                'product_variant_id' => __(ProductionOrderPackagingPlan::DUPLICATE_PRESENTATION_MESSAGE),
            ]);
        }

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
