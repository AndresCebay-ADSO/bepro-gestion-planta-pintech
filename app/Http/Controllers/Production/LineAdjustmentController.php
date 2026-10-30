<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Concerns\RejectsStaleOrderWrites;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreLineAdjustmentRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLineAdjustment;
use App\Services\Production\ProductionOrderLock;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LineAdjustmentController extends Controller
{
    use RejectsStaleOrderWrites;

    public function __construct(
        private readonly ProductionOrderLock $orderLock,
    ) {}

    /**
     * Registrar un ajuste de línea en una orden de producción.
     */
    public function store(StoreLineAdjustmentRequest $request, ProductionOrder $productionOrder): RedirectResponse
    {
        $validated = $request->validated();

        try {
            // Con la OP bloqueada: un ajuste que entra mientras se completa la orden quedaría sin consumir (OPX-03).
            $this->orderLock->write(
                $productionOrder,
                $request->user(),
                'updateOperationalData',
                "No se puede agregar un ajuste de línea a una orden en estado ':status'.",
                fn (ProductionOrder $lockedOrder) => ProductionOrderLineAdjustment::create([
                    'production_order_id' => $lockedOrder->id,
                    'raw_material_id' => $validated['raw_material_id'],
                    'quantity' => $validated['quantity'],
                    'reason' => $validated['reason'],
                    'notes' => $validated['notes'] ?? null,
                    'created_by' => $request->user()->id,
                ]),
            );
        } catch (\DomainException $exception) {
            return $this->staleOrderResponse($exception);
        }

        Inertia::flash('success', 'Ajuste de línea registrado.');

        return redirect()->route('production-orders.show', $productionOrder);
    }

    /**
     * Eliminar un ajuste de línea (solo si la orden no está cerrada).
     */
    public function destroy(Request $request, ProductionOrder $productionOrder, ProductionOrderLineAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('updateOperationalData', $productionOrder);

        if ((int) $adjustment->production_order_id !== $productionOrder->id) {
            abort(404);
        }

        try {
            // Un ajuste borrado mientras se completa la orden dejaría una salida de MP sin el ajuste que la explica.
            $this->orderLock->write(
                $productionOrder,
                $request->user(),
                'updateOperationalData',
                "No se puede quitar un ajuste de línea de una orden en estado ':status'.",
                fn () => $adjustment->delete(),
            );
        } catch (\DomainException $exception) {
            return $this->staleOrderResponse($exception);
        }

        Inertia::flash('success', 'Ajuste de línea eliminado.');

        return redirect()->route('production-orders.show', $productionOrder);
    }
}
