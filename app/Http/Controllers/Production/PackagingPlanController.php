<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Concerns\RejectsStaleOrderWrites;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StorePackagingPlanRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PackagingPlanController extends Controller
{
    use RejectsStaleOrderWrites;

    public function __construct(
        private readonly ProductionOrderLock $orderLock,
    ) {}

    /**
     * Agregar un plan de envasado a una orden de producción.
     */
    public function store(StorePackagingPlanRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Con la OP bloqueada: si otra petición la está completando, esta espera y ve el estado final (OPL-02).
            $this->orderLock->write(
                $productionOrder,
                $request->user(),
                'updateOperationalData',
                "No se puede agregar una presentación a una orden en estado ':status'.",
                fn (ProductionOrder $lockedOrder) => ProductionOrderPackagingPlan::createForVariant((int) $lockedOrder->id, (int) $validated['product_variant_id'], $validated['planned_units']),
            );
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones a la vez con la misma presentación pasan las dos la validación; la segunda choca con el
            // índice único y recibe el mismo mensaje que la validación, no un error 500. El choque sale de la transacción
            // del bloqueo, que se revierte entera (no se reintenta: no es un error de concurrencia).
            throw ValidationException::withMessages([
                'product_variant_id' => __(ProductionOrderPackagingPlan::DUPLICATE_PRESENTATION_MESSAGE),
            ]);
        } catch (\DomainException $exception) {
            return $this->staleOrderResponse($exception);
        }

        Inertia::flash('success', 'Plan de envasado agregado.');

        return redirect()->route('production-orders.show', $productionOrder);
    }

    /**
     * Eliminar un plan de envasado (solo si la orden no está cerrada).
     */
    public function destroy(Request $request, ProductionOrder $productionOrder, ProductionOrderPackagingPlan $plan): RedirectResponse
    {
        $this->authorize('updateOperationalData', $productionOrder);

        if ((int) $plan->production_order_id !== $productionOrder->id) {
            abort(404);
        }

        try {
            $this->orderLock->write(
                $productionOrder,
                $request->user(),
                'updateOperationalData',
                "No se puede quitar una presentación de una orden en estado ':status'.",
                fn () => $plan->delete(),
            );
        } catch (\DomainException $exception) {
            return $this->staleOrderResponse($exception);
        }

        Inertia::flash('success', 'Plan de envasado eliminado.');

        return redirect()->route('production-orders.show', $productionOrder);
    }
}
