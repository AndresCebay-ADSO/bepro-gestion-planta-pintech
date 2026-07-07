<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionOrderStatus;
use App\Enums\QrDocumentType;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\QrDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class QualityInspectionCertificateService
{
    public function generateForCompletedOrder(ProductionOrder $order, int $userId): QrDocument
    {
        $order->loadMissing(['product', 'qrCode.documents', 'qualityResponsibleUser']);

        if ($order->status !== ProductionOrderStatus::Completed) {
            throw new \DomainException('Solo se puede generar certificado para órdenes completadas.');
        }

        $qrCode = $this->createOrReuseQrCode($order, $userId);
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
                'file_name' => "certificado-calidad-{$order->order_number}.pdf",
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

        $lot = $order->lot_number ?? $order->order_number;

        return [
            'certificate_number' => "CC-{$lot}",
            'product_name' => $product->name,
            'lot' => $lot,
            'manufacturing_date' => $order->getManufacturingDate()?->format('d/m/Y'),
            'verification_date' => $order->getVerificationDate()?->format('d/m/Y'),
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

    private function createOrReuseQrCode(ProductionOrder $order, int $userId): QrCode
    {
        $qrCode = QrCode::query()->firstOrNew([
            'production_order_id' => $order->id,
        ]);

        if (! $qrCode->exists) {
            $qrCode->token = $this->generateToken();
            $qrCode->created_by = $userId;
        }

        $qrCode->fill([
            'product_id' => $order->product_id,
            'url' => route('qr.public.show', ['token' => $qrCode->token]),
            'is_active' => true,
        ]);
        $qrCode->save();

        return $qrCode;
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
                Storage::disk('public')->path($order->qualityResponsibleUser->signature_path)
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

    private function generateToken(): string
    {
        do {
            $token = Str::random(40);
        } while (QrCode::query()->where('token', $token)->exists());

        return $token;
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
