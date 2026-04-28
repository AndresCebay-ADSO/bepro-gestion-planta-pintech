<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Enums\ProductionOrderStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\StoreLineAdjustmentRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLineAdjustment;
use Illuminate\Http\RedirectResponse;

class LineAdjustmentController extends Controller
{
    /**
     * Registrar un ajuste de línea en una orden de producción.
     */
    public function store(StoreLineAdjustmentRequest $request, ProductionOrder $order): RedirectResponse
    {
        $this->authorize('update', $order);

        $validated = $request->validated();

        ProductionOrderLineAdjustment::create([
            'production_order_id' => $order->id,
            'raw_material_id' => $validated['raw_material_id'],
            'quantity' => $validated['quantity'],
            'reason' => $validated['reason'],
            'notes' => $validated['notes'] ?? null,
            'created_by' => auth()->id(),
        ]);

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Ajuste de línea registrado.');
    }

    /**
     * Eliminar un ajuste de línea (solo si la orden no está cerrada).
     */
    public function destroy(ProductionOrder $order, ProductionOrderLineAdjustment $adjustment): RedirectResponse
    {
        $this->authorize('update', $order);

        $blockedStatuses = [
            ProductionOrderStatus::Completed,
            ProductionOrderStatus::Cancelled,
        ];

        if (in_array($order->status, $blockedStatuses, true)) {
            return redirect()->route('production-orders.show', $order)
                ->with('error', 'No se pueden eliminar ajustes de una orden completada o cancelada.');
        }

        if ((int) $adjustment->production_order_id !== $order->id) {
            abort(404);
        }

        $adjustment->delete();

        return redirect()->route('production-orders.show', $order)
            ->with('success', 'Ajuste de línea eliminado.');
    }
}
