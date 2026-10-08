<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\Production\ConsumeRemnantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\ConsumeRemnantRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Services\AvailableRemnantsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class RemnantConsumptionController extends Controller
{
    public function __construct(
        private readonly AvailableRemnantsService $availableRemnants,
    ) {}

    /**
     * Refresca la lista de saldos que la orden puede consumir: los disponibles de su bodega, de cualquier producto
     * (decisión del 2026-09-23), cada uno con el producto y el color de su orden de origen.
     */
    public function availableRemnants(ProductionOrder $productionOrder): JsonResponse
    {
        $this->authorize('updateOperationalData', $productionOrder);

        return response()->json($this->availableRemnants->forOrder($productionOrder));
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
