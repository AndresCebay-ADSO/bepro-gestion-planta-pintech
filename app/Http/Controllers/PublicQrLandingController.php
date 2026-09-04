<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductDocument;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Services\QrImageService;
use App\Services\TimezoneService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicQrLandingController extends Controller
{
    public function __construct(
        private readonly TimezoneService $timezoneService,
    ) {}

    public function show(string $token): InertiaResponse
    {
        $qrCode = QrCode::query()
            ->active()
            ->with([
                'product.productDocuments' => fn ($query) => $query->current()->latest('id'),
                'productionOrder',
                'documents' => fn ($query) => $query->current()->latest('id'),
            ])
            ->where('token', $token)
            ->firstOrFail();

        return Inertia::render('Public/QrLanding/Show', [
            'product' => [
                'name' => $qrCode->product->name,
                'description' => $qrCode->product->description,
            ],
            'lot' => [
                'number' => $qrCode->productionOrder->order_number,
                'manufacturing_date' => $this->timezoneService->formatPlantDate($qrCode->productionOrder->getManufacturingDate()),
                'verification_date' => $this->timezoneService->formatPlantDate($qrCode->productionOrder->getVerificationDate()),
            ],
            'documents' => [
                ...$qrCode->product->productDocuments->map(fn (ProductDocument $document) => [
                    'id' => $document->id,
                    'name' => $document->file_name,
                    'type' => $document->document_type->label(),
                    'size' => $this->formatFileSize($document->file_size),
                    'date' => $document->created_at?->toDateString(),
                    'download_url' => route('qr.public.product-documents.download', [
                        'token' => $qrCode->token,
                        'document' => $document,
                    ]),
                ])->all(),
                ...$qrCode->documents->map(fn (QrDocument $document) => [
                    'id' => $document->id,
                    'name' => $document->file_name,
                    'type' => $document->document_type->label(),
                    'size' => $this->formatFileSize($document->file_size),
                    'date' => $document->created_at?->toDateString(),
                    'download_url' => route('qr.public.documents.download', [
                        'token' => $qrCode->token,
                        'document' => $document,
                    ]),
                ])->all(),
            ],
        ]);
    }

    public function downloadDocument(string $token, QrDocument $document): StreamedResponse
    {
        $qrCode = QrCode::query()->active()->where('token', $token)->firstOrFail();

        abort_unless($document->qr_code_id === $qrCode->id && $document->is_current, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?? 'application/pdf']
        );
    }

    public function downloadProductDocument(string $token, ProductDocument $document): StreamedResponse
    {
        $qrCode = QrCode::query()->active()->where('token', $token)->firstOrFail();

        abort_unless($document->product_id === $qrCode->product_id && $document->is_current, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?? 'application/pdf']
        );
    }

    public function qrImage(string $token, QrImageService $service): Response
    {
        $qrCode = QrCode::query()->active()->where('token', $token)->firstOrFail();

        $png = $service->generatePng($qrCode);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        if ($bytes < 1048576) {
            return round($bytes / 1024).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
