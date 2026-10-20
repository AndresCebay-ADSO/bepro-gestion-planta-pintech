<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StorePackagingPlanRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PackagingPlanController extends Controller
{
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
                // En su propia transacción (un savepoint): si choca, PostgreSQL revierte solo esta inserción y la
                // conexión sigue usable.
                fn (ProductionOrder $lockedOrder) => DB::transaction(fn () => ProductionOrderPackagingPlan::createForVariant((int) $lockedOrder->id, (int) $validated['product_variant_id'], $validated['planned_units'])),
            );
        } catch (UniqueConstraintViolationException) {
            // Dos peticiones a la vez con la misma presentación pasan las dos la validación; la segunda choca con el
            // índice único y recibe el mismo mensaje que la validación, no un error 500.
            throw ValidationException::withMessages([
                'product_variant_id' => __(ProductionOrderPackagingPlan::DUPLICATE_PRESENTATION_MESSAGE),
            ]);
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Plan de envasado agregado.');
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
            return back()->with('error', $exception->getMessage());
        }

        return redirect()->route('production-orders.show', $productionOrder)
            ->with('success', 'Plan de envasado eliminado.');
    }
}
