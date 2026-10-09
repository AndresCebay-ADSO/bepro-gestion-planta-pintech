<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\LabelFormat;
use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\QrCode;
use App\Services\LabelNameFitterService;
use App\Services\ProductionOrderQrCodeService;
use App\Services\QrImageService;
use App\Services\TimezoneService;
use Barryvdh\DomPDF\PDF;
use Dompdf\FontMetrics;
use Illuminate\Support\Facades\DB;

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
        private readonly LabelNameFitterService $nameFitter,
        private readonly TimezoneService $timezoneService,
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
        int $userId,
    ): string {
        // Con la orden bloqueada, como al cancelarla: si las dos ocurren a la vez, una espera a la otra. Así no nace un
        // QR activo en una orden que se acaba de cancelar (la política revisó el estado antes, sin bloquear).
        $qrCode = DB::transaction(function () use ($order, $userId) {
            $lockedOrder = ProductionOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($lockedOrder->status === ProductionOrderStatus::Cancelled) {
                throw new \DomainException('Esta orden se canceló: sus estampitas ya no se imprimen.');
            }

            return $this->qrCodeService->ensureForLabels($lockedOrder, $userId);
        }, attempts: 3);

        // Una sola instancia de DomPDF: la que mide el nombre es la que dibuja el PDF.
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
     * @param  FontMetrics  $metrics  las de la instancia de DomPDF que dibuja el PDF, para medir el nombre.
     * @return array{name: string, name_size: int, presentation: string, lot: int, manufactured_on: string, verify_on: string, qr: string}
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
        $name = $this->nameFitter->fit($order->product->name, $order->color, $format->contentWidthPt(), $metrics);

        return [
            'name' => $name['name'],
            'name_size' => $name['size'],
            'presentation' => implode(' · ', array_filter(
                [$variant->presentation_label, $variant->code],
                fn (?string $part): bool => filled($part),
            )),
            'lot' => $order->lot_number,
            // En hora de planta, como el certificado y la página del QR: la base guarda UTC (B54).
            'manufactured_on' => $this->timezoneService->formatPlantDate($order->getManufacturingDate()),
            'verify_on' => $this->timezoneService->formatPlantDate($order->getVerificationDate()),
            'qr' => 'data:image/png;base64,'.base64_encode($this->qrImageService->generatePng($qrCode)),
        ];
    }
}
