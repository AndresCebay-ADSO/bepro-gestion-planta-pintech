<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\QrDocumentType;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\User;
use App\Support\ImageDataUri;
use App\Support\NumberFormat;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QualityInspectionCertificateService
{
    /** Largo de `qr_documents.file_name`. */
    private const FILE_NAME_MAX_LENGTH = 255;

    public function __construct(
        private readonly TimezoneService $timezoneService,
        private readonly ProductionOrderQrCodeService $qrCodeService,
        private readonly VersionedDocumentStore $documentStore,
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

        return $this->storeNewVersion($order, $qrCode, $this->renderPdf($order, $this->buildPayload($order)), $userId);
    }

    /**
     * Carga a mano el certificado de una orden completada, para cuando el generado no sirve o no se pudo generar. Queda
     * como la versión vigente, con el mismo nombre de descarga que uno generado.
     */
    public function storeManualUpload(QrCode $qrCode, UploadedFile $file, int $userId): QrDocument
    {
        if (! $qrCode->acceptsCertificate()) {
            throw new \DomainException('Solo se puede subir el certificado de una orden completada.');
        }

        return $this->storeNewVersion($qrCode->productionOrder, $qrCode, $file->getContent(), $userId);
    }

    private function storeNewVersion(ProductionOrder $order, QrCode $qrCode, string $content, int $userId): QrDocument
    {
        /** @var QrDocument */
        return $this->documentStore->store(
            owner: $qrCode,
            relation: 'documents',
            type: QrDocumentType::QualityCertificate,
            content: $content,
            fileName: $this->fileName($order),
            pathFor: fn (int $version): string => "quality-certificates/{$order->order_number}/certificado-calidad-v{$version}-".Str::ulid().'.pdf',
            userId: $userId,
        );
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
            'tests' => array_values(array_filter([
                $this->numericTest(
                    name: 'VISCOSIDAD',
                    unit: 'KU',
                    result: $order->viscosity_ku,
                    lower: $product->quality_viscosity_lower,
                    upper: $product->quality_viscosity_upper
                ),
                $this->numericTest(
                    name: 'FINURA HEGMAN',
                    unit: 'HEGMAN',
                    result: $order->grinding_hg,
                    lower: $product->quality_fineness_lower,
                    upper: $product->quality_fineness_upper
                ),
                $this->numericTest(
                    name: 'SÓLIDOS',
                    unit: '%',
                    result: $order->quality_solids,
                    lower: $product->quality_solids_lower,
                    upper: $product->quality_solids_upper
                ),
                [
                    'name' => 'APARIENCIA',
                    'unit' => 'CUALITATIVA',
                    'result' => 'OK',
                    'lower_limit' => 'NO SEPARACIÓN',
                    'upper_limit' => 'AP. COLOR ESTABLE',
                ],
            ])),
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
     * @param  array<string, mixed>  $payload
     */
    private function renderPdf(ProductionOrder $order, array $payload): string
    {
        $logoBase64 = ImageDataUri::fromFile(public_path('images/logo-bepro-calidad.png'));

        $signatureBase64 = null;
        if ($order->qualityResponsibleUser?->signature_path) {
            $signatureBase64 = ImageDataUri::fromFile(
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

        return $pdf->output();
    }

    /**
     * Sin límites ni medición, la prueba no aplica a este producto (un ajustador no lleva KU) y no sale en el certificado.
     * Si se midió, sí sale: un certificado no calla un resultado porque al producto le falten los límites.
     *
     * @return array{name: string, unit: string, result: string, lower_limit: string, upper_limit: string}|null
     */
    private function numericTest(string $name, string $unit, ?string $result, ?string $lower, ?string $upper): ?array
    {
        if ($lower === null && $upper === null && $result === null) {
            return null;
        }

        $hasResult = $result !== null;

        return [
            'name' => $name,
            'unit' => $unit,
            // Mediciones con el decimal de su columna (decimal:1) y coma decimal, como el PDF de la OP.
            'result' => $hasResult ? NumberFormat::measurement($result, 1) : 'SIN RESULTADO',
            'lower_limit' => NumberFormat::measurement($lower, 1),
            'upper_limit' => NumberFormat::measurement($upper, 1),
        ];
    }
}
