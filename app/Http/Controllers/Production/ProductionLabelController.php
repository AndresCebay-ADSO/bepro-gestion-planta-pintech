<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\Production\PrintProductionLabelsAction;
use App\Enums\LabelFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\PrintProductionLabelsRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Estampitas de lote de una fila del plan de envasado: un PDF de una página por estampita que el navegador manda a la
 * DYMO en tamaño real.
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
            $labels = $this->printLabels->execute(
                order: $productionOrder,
                plan: $plan,
                quantity: (int) $request->validated('quantity'),
                format: $format,
                userId: (int) $request->user()->id,
            );
        } catch (\DomainException $exception) {
            return back()->with('error', $exception->getMessage());
        }

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView($format->view(), ['labels' => $labels]);
        $pdf->setPaper($format->paper());

        return $pdf->stream("estampitas-lote-{$productionOrder->lot_number}-{$plan->productVariant->code}.pdf");
    }
}
