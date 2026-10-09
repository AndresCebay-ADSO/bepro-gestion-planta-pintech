<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\QrDocumentType;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class QualityInspectionCertificateService
{
    /** Largo de `qr_documents.file_name`. */
    private const FILE_NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly TimezoneService $timezoneService,
        private readonly ProductionOrderQrCodeService $qrCodeService,
    ) {}

    public function generateForCompletedOrder(ProductionOrder $order, int $userId): QrDocument
    {
        $order->loadMissing(['product', 'qrCode.documents', 'qualityResponsibleUser']);

        if ($order->status !== ProductionOrderStatus::Completed) {
            throw new \DomainException('Solo se puede generar certificado para órdenes completadas.');
        }

        // Si ya se imprimieron estampitas, reutiliza ese QR: el que está pegado en el envase. No lo reactiva si un
        // administrador lo desactivó con la orden abierta.
        $qrCode = $this->qrCodeService->ensureForCertificate($order, $userId);
        $version = $this->nextVersion($qrCode);
        $payload = $this->buildPayload($order);
        $storedPdf = $this->storePdf($order, $payload, $version);

        return DB::transaction(function () use ($qrCode, $order, $storedPdf, $version, $userId): QrDocument {
            QrDocument::query()
                ->where('qr_code_id', $qrCode->id)
                ->where('document_type', QrDocumentType::QualityCertificate->value)
                ->update(['is_current' => false]);

            /** @var QrDocument */
            return QrDocument::create([
                'qr_code_id' => $qrCode->id,
                'document_type' => QrDocumentType::QualityCertificate,
                'file_name' => $this->fileName($order),
                'file_path' => $storedPdf['path'],
                'file_size' => $storedPdf['size'],
                'mime_type' => 'application/pdf',
                'version' => $version,
                'is_current' => true,
                'uploaded_by' => $userId,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildPayload(ProductionOrder $order): array
    {
        $order->loadMissing(['product', 'qualityResponsibleUser']);
        $product = $order->product;
        $signer = $order->qualityResponsibleUser;

        return [
            'certificate_number' => "CC-{$order->lot_number}",
            // Con el color que pidió el cliente (3.4): el certificado es de ese lote.
            'product_name' => $order->productDisplayName(),
            'lot' => $order->lot_number,
            'manufacturing_date' => $this->timezoneService->formatPlantDate($order->getManufacturingDate()),
            'verification_date' => $this->timezoneService->formatPlantDate($order->getVerificationDate()),
            'responsible_name' => $signer?->name ?? $order->responsible_name ?? 'N/A',
            'responsible_role' => $signer?->job_title ?? 'N/A',
            'tests' => [
                $this->numericTest(
                    name: 'VISCOSIDAD',
                    unit: 'KU',
                    result: $order->viscosity_ku !== null ? (float) $order->viscosity_ku : null,
                    lower: $product->quality_viscosity_lower !== null ? (float) $product->quality_viscosity_lower : null,
                    upper: $product->quality_viscosity_upper !== null ? (float) $product->quality_viscosity_upper : null
                ),
                $this->numericTest(
                    name: 'FINURA HEGMAN',
                    unit: 'HEGMAN',
                    result: $order->grinding_hg !== null ? (float) $order->grinding_hg : null,
                    lower: $product->quality_fineness_lower !== null ? (float) $product->quality_fineness_lower : null,
                    upper: $product->quality_fineness_upper !== null ? (float) $product->quality_fineness_upper : null
                ),
                $this->numericTest(
                    name: 'SÓLIDOS',
                    unit: '%',
                    result: $order->quality_solids !== null ? (float) $order->quality_solids : null,
                    lower: $product->quality_solids_lower !== null ? (float) $product->quality_solids_lower : null,
                    upper: $product->quality_solids_upper !== null ? (float) $product->quality_solids_upper : null
                ),
                [
                    'name' => 'APARIENCIA',
                    'unit' => 'CUALITATIVA',
                    'result' => 'OK',
                    'lower_limit' => 'NO SEPARACIÓN',
                    'upper_limit' => 'AP. COLOR ESTABLE',
                ],
            ],
        ];
    }

    /**
     * Nombre con que el cliente descarga el certificado, como la planta nombra los suyos:
     * «{producto} LOTE {lote} {fecha de fabricación}.pdf». La fecha es la de fabricación del lote (creación de la OP),
     * la misma que imprime el certificado.
     */
    public function fileName(ProductionOrder $order): string
    {
        $order->loadMissing('product');

        // Con el color de la orden (3.4). `/` y `\` rompen la descarga: Symfony los rechaza en el nombre del archivo.
        // Qué caracteres admite el nombre de un producto se decide en 3.6 (repaso de Form Requests).
        $productName = str_replace(['/', '\\'], '-', $order->productDisplayName());
        $manufacturedOn = $this->timezoneService->formatPlantDate($order->getManufacturingDate(), 'd-m-Y');
        $suffix = " LOTE {$order->lot_number} {$manufacturedOn}.pdf";

        // `qr_documents.file_name` es varchar(255) y el producto (150) con el color (100) más el lote y la fecha no
        // siempre caben: se recorta el nombre, nunca el lote ni la fecha, que identifican el archivo.
        $productName = rtrim(mb_substr($productName, 0, self::FILE_NAME_MAX_LENGTH - mb_strlen($suffix)));

        return $productName.$suffix;
    }

    /**
     * @return array{path: string, size: int}
     */
    private function storePdf(ProductionOrder $order, array $payload, int $version): array
    {
        $logoBase64 = $this->assetBase64(public_path('images/logo-bepro-calidad.png'));

        $signatureBase64 = null;
        if ($order->qualityResponsibleUser?->signature_path) {
            $signatureBase64 = $this->assetBase64(
                Storage::disk(User::SIGNATURE_DISK)->path($order->qualityResponsibleUser->signature_path)
            );
        }

        /** @var \Barryvdh\DomPDF\PDF $pdf */
        $pdf = Pdf::loadView('pdf.quality-inspection-certificate', [
            'certificate' => $payload,
            'logoBase64' => $logoBase64,
            'signatureBase64' => $signatureBase64,
        ]);
        $pdf->setPaper('letter');

        $content = $pdf->output();
        $path = "quality-certificates/{$order->order_number}/certificado-calidad-v{$version}.pdf";

        Storage::disk('local')->put($path, $content);

        return [
            'path' => $path,
            'size' => strlen($content),
        ];
    }

    private function nextVersion(QrCode $qrCode): int
    {
        return ((int) $qrCode->documents()
            ->where('document_type', QrDocumentType::QualityCertificate->value)
            ->max('version')) + 1;
    }

    /**
     * @return array{name: string, unit: string, result: string, lower_limit: string, upper_limit: string}
     */
    private function numericTest(string $name, string $unit, ?float $result, ?float $lower, ?float $upper): array
    {
        $hasResult = $result !== null;

        return [
            'name' => $name,
            'unit' => $unit,
            'result' => $hasResult ? $this->formatNumber((float) $result) : 'SIN RESULTADO',
            'lower_limit' => ($lower !== null) ? $this->formatNumber($lower) : '—',
            'upper_limit' => ($upper !== null) ? $this->formatNumber($upper) : '—',
        ];
    }

    private function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private function assetBase64(string $path): ?string
    {
        if (! file_exists($path)) {
            return null;
        }

        $mimeType = mime_content_type($path) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode((string) file_get_contents($path));
    }
}
