<?php

declare(strict_types=1);

namespace App\Actions\Production;

use App\Enums\LabelFormat;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Services\LabelNameFitterService;
use App\Services\ProductionOrderQrCodeService;
use App\Services\QrImageService;
use App\Services\TimezoneService;
use Illuminate\Support\Facades\DB;

/**
 * Estampitas de lote de una presentación del plan de envasado. Se imprimen antes de completar la orden: el lote y las
 * fechas existen desde que se crea, y el QR se crea aquí (si aún no existe) y el certificado lo reutiliza al completar.
 * Queda en la auditoría de la orden quién imprimió cuántas.
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
     * Una entrada por estampita, lista para la plantilla del formato.
     *
     * @return list<array{name: string, name_size: int, presentation: string, lot: int, manufactured_on: string, verify_on: string, qr: string}>
     *
     * @throws \DomainException si el QR de la orden está desactivado.
     */
    public function execute(
        ProductionOrder $order,
        ProductionOrderPackagingPlan $plan,
        int $quantity,
        LabelFormat $format,
        int $userId,
    ): array {
        $order->loadMissing('product');
        $plan->loadMissing('productVariant');
        $variant = $plan->productVariant;

        $qrCode = DB::transaction(function () use ($order, $plan, $variant, $quantity, $format, $userId) {
            $qrCode = $this->qrCodeService->ensureForLabels($order, $userId);

            activity('ordenes_produccion')
                ->performedOn($order)
                ->event('labels_printed')
                ->withProperties([
                    'packaging_plan_id' => $plan->id,
                    'product_variant_id' => $variant->id,
                    'quantity' => $quantity,
                    'format' => $format->value,
                ])
                ->log("{$quantity} estampitas impresas del lote {$order->lot_number} ({$variant->presentation_label})");

            return $qrCode;
        });

        // El nombre con el color de la orden, ajustado a dos líneas (3.4: nunca se recorta el color).
        $name = $this->nameFitter->fit($order->product->name, $order->color, $format->contentWidthPt());

        $label = [
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

        return array_fill(0, $quantity, $label);
    }
}
