<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filters\QrCodeFilter;
use App\Http\Requests\QrCodes\IndexQrCodeRequest;
use App\Http\Requests\QrCodes\UpdateQrCodeRequest;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Services\QrImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QrCodeController extends Controller
{
    public function index(IndexQrCodeRequest $request): Response
    {
        $qrCodes = (new QrCodeFilter($request))
            ->apply(QrCode::query())
            ->with([
                'product:id,name,code',
                'productionOrder:id,order_number,lot_number',
            ])
            ->withCount('documents')
            ->latest('id')
            ->paginate(15)
            ->onEachSide(1)
            ->withQueryString()
            ->through(function (QrCode $qrCode): array {
                return [
                    'id' => $qrCode->id,
                    'token' => $qrCode->token,
                    'token_short' => substr($qrCode->token, 0, 8).'…',
                    'is_active' => $qrCode->is_active,
                    'product' => $qrCode->product ? [
                        'id' => $qrCode->product->id,
                        'name' => $qrCode->product->name,
                        'code' => $qrCode->product->code,
                    ] : null,
                    'production_order' => $qrCode->productionOrder ? [
                        'id' => $qrCode->productionOrder->id,
                        'order_number' => $qrCode->productionOrder->order_number,
                        'lot_number' => $qrCode->productionOrder->lot_number,
                    ] : null,
                    'documents_count' => $qrCode->documents_count,
                    'created_at' => $qrCode->created_at?->toISOString(),
                ];
            });

        return Inertia::render('QrCodes/Index', [
            'qrCodes' => $qrCodes,
            'filters' => $request->validated(),
            'can' => [
                'viewAny' => $request->user()?->can('viewAny', QrCode::class) ?? false,
                'update' => $request->user()?->can('update', QrCode::class) ?? false,
            ],
        ]);
    }

    public function show(QrCode $qrCode): Response
    {
        $this->authorize('view', $qrCode);

        $qrCode->load([
            'product:id,name,code,description',
            'productionOrder:id,order_number,lot_number,completion_date,planned_date',
            'createdBy:id,name',
            'documents' => fn ($query) => $query
                ->with('uploadedBy:id,name')
                ->orderByDesc('is_current')
                ->orderByDesc('version'),
        ]);

        $landingUrl = route('qr.public.show', ['token' => $qrCode->token]);
        $imageUrl = route('qr-codes.qr-image', ['qrCode' => $qrCode]);

        return Inertia::render('QrCodes/Show', [
            'qrCode' => [
                'id' => $qrCode->id,
                'token' => $qrCode->token,
                'is_active' => $qrCode->is_active,
                'landing_url' => $landingUrl,
                'image_url' => $imageUrl,
                'created_at' => $qrCode->created_at?->toISOString(),
                'created_by' => $qrCode->createdBy ? [
                    'id' => $qrCode->createdBy->id,
                    'name' => $qrCode->createdBy->name,
                ] : null,
                'product' => $qrCode->product ? [
                    'id' => $qrCode->product->id,
                    'name' => $qrCode->product->name,
                    'code' => $qrCode->product->code,
                    'description' => $qrCode->product->description,
                ] : null,
                'production_order' => $qrCode->productionOrder ? [
                    'id' => $qrCode->productionOrder->id,
                    'order_number' => $qrCode->productionOrder->order_number,
                    'lot_number' => $qrCode->productionOrder->lot_number,
                    'completion_date' => $qrCode->productionOrder->completion_date?->toDateString(),
                    'planned_date' => $qrCode->productionOrder->planned_date?->toDateString(),
                ] : null,
                'documents' => $qrCode->documents->map(fn ($document) => [
                    'id' => $document->id,
                    'file_name' => $document->file_name,
                    'document_type' => $document->document_type->label(),
                    'version' => $document->version,
                    'is_current' => $document->is_current,
                    'created_at' => $document->created_at?->toDateString(),
                    'uploaded_by' => $document->uploadedBy ? [
                        'id' => $document->uploadedBy->id,
                        'name' => $document->uploadedBy->name,
                    ] : null,
                    'download_url' => route('qr-codes.documents.download', [
                        'qrCode' => $qrCode,
                        'document' => $document,
                    ]),
                ])->values(),
            ],
            'can' => [
                'update' => auth()->user()?->can('update', $qrCode) ?? false,
            ],
        ]);
    }

    public function update(UpdateQrCodeRequest $request, QrCode $qrCode): RedirectResponse
    {
        $qrCode->update(['is_active' => $request->validated('is_active')]);

        return redirect()->back()->with('success', 'Estado del QR actualizado.');
    }

    public function qrImage(QrCode $qrCode, QrImageService $service): HttpResponse
    {
        $this->authorize('view', $qrCode);

        $png = $service->generatePng($qrCode);

        return response($png, 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function downloadDocument(QrCode $qrCode, QrDocument $document): StreamedResponse
    {
        $this->authorize('view', $qrCode);

        abort_unless($document->qr_code_id === $qrCode->id, 404);
        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->response(
            $document->file_path,
            $document->file_name,
            ['Content-Type' => $document->mime_type ?? 'application/pdf']
        );
    }
}
