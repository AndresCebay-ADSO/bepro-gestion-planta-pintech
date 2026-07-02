<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\Production\ConsumeRemnantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\ConsumeRemnantRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RemnantConsumptionController extends Controller
{
    /**
     * Devuelve los saldos disponibles que pueden ser consumidos por la orden indicada
     * (mismo producto, misma bodega, estado disponible).
     */
    public function availableRemnants(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('updateOperationalData', $productionOrder);

        $remnants = ProductionRemnant::query()
            ->with(['sourceOrder:id,order_number'])
            ->available()
            ->where('warehouse_id', $productionOrder->warehouse_id)
            ->orderBy('created_at', 'asc') // FIFO
            ->limit(50)
            ->get()
            ->map(fn (ProductionRemnant $r) => [
                'id' => $r->id,
                'source_order_number' => $r->sourceOrder->order_number,
                'available_quantity_gallons' => (float) $r->available_quantity_gallons,
                'density_kg_per_gallon' => (float) $r->density_kg_per_gallon,
            ])
            ->values();

        return response()->json($remnants);
    }

    /**
     * Consume el saldo indicado en la orden de producción.
     */
    public function store(
        ConsumeRemnantRequest $request,
        ProductionOrder $productionOrder,
        ConsumeRemnantAction $consumeRemnantAction
    ): RedirectResponse {
        $remnant = ProductionRemnant::findOrFail($request->validated('remnant_id'));

        $consumeRemnantAction->execute(
            remnant: $remnant,
            targetOrder: $productionOrder,
            quantityGallons: (string) $request->validated('quantity_gallons'),
            userId: $request->user()->id,
            notes: $request->validated('notes')
        );

        return back()->with('success', __('Saldo de PT consumido exitosamente.'));
    }
}
