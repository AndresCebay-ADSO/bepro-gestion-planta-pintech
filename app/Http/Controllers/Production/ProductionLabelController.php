<?php

declare(strict_types=1);

namespace App\Http\Controllers\Production;

use App\Actions\Production\PrintProductionLabelsAction;
use App\Enums\LabelFormat;
use App\Http\Controllers\Controller;
use App\Http\Requests\Production\PrintProductionLabelsRequest;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

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
    ): Response {
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
            // La estampita se abre en otra pestaña: volver atrás ahí cargaría una segunda copia de la orden.
            return response()->view('labels.print-error', [
                'message' => $exception->getMessage(),
                'orderUrl' => route('production-orders.show', $productionOrder),
            ], 409);
        }

        // El código de la presentación es texto libre: `/` y `\` no caben en un nombre de archivo (Symfony los rechaza), y
        // las comillas o la «Ñ» van en el nombre UTF-8 con un respaldo ASCII sin `%`, como pide la cabecera.
        $fileName = str_replace(['/', '\\'], '-', "estampita-lote-{$productionOrder->lot_number}-{$plan->productVariant->code}.pdf");
        $asciiFileName = str_replace('%', '', Str::ascii($fileName));

        // En línea: se abre en el visor del navegador, que la manda a la DYMO.
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_INLINE, $fileName, $asciiFileName),
        ]);
    }
}
