<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ProductDocument;
use App\Models\QrCode;
use App\Models\QrDocument;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\QrCode as QrCodeImage;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PublicQrLandingController extends Controller
{
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
                'manufacturing_date' => $qrCode->productionOrder->getManufacturingDate()?->format('d/m/Y'),
                'verification_date' => $qrCode->productionOrder->getVerificationDate()?->format('d/m/Y'),
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

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function downloadProductDocument(string $token, ProductDocument $document): StreamedResponse
    {
        $qrCode = QrCode::query()->active()->where('token', $token)->firstOrFail();

        abort_unless($document->product_id === $qrCode->product_id && $document->is_current, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function qrImage(string $token): Response
    {
        $qrCode = QrCode::query()->active()->where('token', $token)->firstOrFail();

        $url = route('qr.public.show', ['token' => $qrCode->token]);

        $image = new QrCodeImage(
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
        );

        $logoPath = public_path('images/beprologoqr.png');
        $logo = file_exists($logoPath)
            ? new Logo(path: $logoPath, resizeToWidth: 80, punchoutBackground: true)
            : null;

        $png = (new PngWriter)->write($image, $logo)->getString();

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
