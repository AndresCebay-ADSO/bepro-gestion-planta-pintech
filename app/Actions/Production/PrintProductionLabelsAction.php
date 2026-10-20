<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\LabelFormat;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\QrCode;
use App\Models\User;
use App\Services\LabelTextFitterService;
use App\Services\Production\ProductionOrderLock;
use App\Services\ProductionOrderQrCodeService;
use App\Services\QrImageService;
use App\Services\TimezoneService;
use Barryvdh\DomPDF\PDF;
use Dompdf\FontMetrics;

/**
 * Estampita de lote de una presentación del plan de envasado. Se imprime antes de completar la orden: el lote y las
 * fechas existen desde que se crea, y el QR se crea aquí (si aún no existe) y el certificado lo reutiliza al completar.
 * El PDF es una sola estampita: las copias se eligen en el diálogo de impresión del navegador, así que la auditoría
 * registra quién la abrió para imprimir, no cuántas salieron.
 */
class PrintProductionLabelsAction
{
    public function __construct(
        private readonly ProductionOrderQrCodeService $qrCodeService,
        private readonly QrImageService $qrImageService,
        private readonly LabelTextFitterService $textFitter,
        private readonly TimezoneService $timezoneService,
        private readonly ProductionOrderLock $orderLock,
    ) {}

    /**
     * El PDF de una estampita.
     *
     * @throws \DomainException si la orden se canceló o su QR está desactivado.
     */
    public function execute(
        ProductionOrder $order,
        ProductionOrderPackagingPlan $plan,
        LabelFormat $format,
        User $user,
    ): string {
        // Con la orden bloqueada, como al cancelarla: si las dos ocurren a la vez, una espera a la otra. Así no nace un
        // QR activo en una orden que se acaba de cancelar (la política revisó el estado antes, sin bloquear).
        $qrCode = $this->orderLock->write(
            $order,
            $user,
            'printLabels',
            'Esta orden se canceló: sus estampitas ya no se imprimen.',
            fn (ProductionOrder $lockedOrder) => $this->qrCodeService->ensureForLabels($lockedOrder, (int) $user->id),
        );

        // Una sola instancia de DomPDF: la que mide el texto es la que dibuja el PDF.
        /** @var PDF $pdf */
        $pdf = app('dompdf.wrapper');
        $label = $this->buildLabel($order, $plan, $format, $qrCode, $pdf->getDomPDF()->getFontMetrics());

        $pdf->loadView($format->view(), ['format' => $format, 'labels' => [$label]]);
        $pdf->setPaper($format->paper());
        $output = $pdf->output();

        // Después del PDF: si no se genera, no queda una impresión que no existió.
        activity('ordenes_produccion')
            ->performedOn($order)
            ->event('labels_printed')
            ->withProperties([
                'packaging_plan_id' => $plan->id,
                'product_variant_id' => $plan->productVariant->id,
                'format' => $format->value,
            ])
            ->log("Estampita del lote {$order->lot_number} ({$plan->productVariant->presentation_label}) abierta para imprimir");

        return $output;
    }

    /**
     * Los datos de la estampita, listos para la plantilla del formato.
     *
     * @param  FontMetrics  $metrics  las de la instancia de DomPDF que dibuja el PDF, para medir el texto.
     * @return array{name: string, name_size: int, presentation_lines: list<string>, presentation_size: int, lot: int, manufactured_on: string, verify_on: string, qr: string}
     */
    public function buildLabel(
        ProductionOrder $order,
        ProductionOrderPackagingPlan $plan,
        LabelFormat $format,
        QrCode $qrCode,
        FontMetrics $metrics,
    ): array {
        $order->loadMissing('product');
        $plan->loadMissing('productVariant');
        $variant = $plan->productVariant;

        // El nombre con el color de la orden, ajustado a dos líneas (3.4: nunca se recorta el color).
        $name = $this->textFitter->fitName($order->product->name, $order->color, $format->contentWidthPt(), $metrics);
        // El código es texto libre de hasta 80 caracteres: sin ajustar, una línea partida pasaba la estampita a 2 páginas.
        $presentation = $this->textFitter->fitPresentation($variant->presentation_label, $variant->code, $format->presentationWidthPt(), $metrics);

        return [
            'name' => $name['name'],
            'name_size' => $name['size'],
            'presentation_lines' => $presentation['lines'],
            'presentation_size' => $presentation['size'],
            'lot' => $order->lot_number,
            // En hora de planta, como el certificado y la página del QR: la base guarda UTC (B54).
            'manufactured_on' => $this->timezoneService->formatPlantDate($order->getManufacturingDate()),
            'verify_on' => $this->timezoneService->formatPlantDate($order->getVerificationDate()),
            'qr' => 'data:image/png;base64,'.base64_encode($this->qrImageService->generatePng($qrCode)),
        ];
    }
}
