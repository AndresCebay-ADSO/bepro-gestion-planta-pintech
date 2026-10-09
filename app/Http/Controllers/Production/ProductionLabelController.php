<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\Production\PrintProductionLabelsAction;
use App\Enums\LabelFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\PrintProductionLabelsRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Estampita de lote de una fila del plan de envasado: un PDF de una página que el navegador manda a la DYMO en tamaño
 * real, con las copias que el operario elija en el diálogo de impresión.
 */
class ProductionLabelController extends Controller
{
    public function __construct(
        private readonly PrintProductionLabelsAction $printLabels,
    ) {}

    public function print(
        PrintProductionLabelsRequest $request,
        ProductionOrder $productionOrder,
        ProductionOrderPackagingPlan $plan,
    ): Response|RedirectResponse {
        if ((int) $plan->production_order_id !== $productionOrder->id) {
            abort(404);
        }

        // Un solo rollo por ahora: el formato se elegirá al imprimir cuando haya otro.
        $format = LabelFormat::Dymo57x32;

        try {
            $pdf = $this->printLabels->execute(
                order: $productionOrder,
                plan: $plan,
                format: $format,
                userId: (int) $request->user()->id,
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        // En línea: se abre en el visor del navegador, que la manda a la DYMO.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"estampita-lote-{$productionOrder->lot_number}-{$plan->productVariant->code}.pdf\"",
        ]);
    }
}
