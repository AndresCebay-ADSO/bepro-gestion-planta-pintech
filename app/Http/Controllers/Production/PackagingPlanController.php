<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Enums\ProductionOrderStatus;
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
    public function store(StorePackagingPlanRequest $request, ProductionOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        ProductionOrderPackagingPlan::create([
            'production_order_id' => $order->id,
            'product_variant_id' => $validated['product_variant_id'],
            'planned_units' => $validated['planned_units'],
        ]);

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Plan de envasado agregado.');
    }

    /**
     * Eliminar un plan de envasado (solo si la orden no está cerrada).
     */
    public function destroy(ProductionOrder $order, ProductionOrderPackagingPlan $plan): RedirectResponse
    {
        $this->authorize('update', $order);

        $blockedStatuses = [
            ProductionOrderStatus::Completed,
            ProductionOrderStatus::Cancelled,
        ];

        if (in_array($order->status, $blockedStatuses, true)) {
            return redirect()->route('production-orders.show', $order)
                ->with('error', 'No se pueden eliminar planes de envasado de una orden completada o cancelada.');
        }

        if ((int) $plan->production_order_id !== $order->id) {
            abort(404);
        }

        $plan->delete();

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Plan de envasado eliminado.');
    }
}
