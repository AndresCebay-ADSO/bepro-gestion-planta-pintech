<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\QrDocumentType;
use App\Http\Requests\Products\StoreProductDocumentRequest;
use App\Models\Product;
use App\Models\ProductDocument;
use App\Services\ProductDocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductDocumentController extends Controller
{
    public function __construct(
        protected ProductDocumentService $productDocumentService
    ) {}

    public function store(StoreProductDocumentRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $documentType = QrDocumentType::from($validated['document_type']);

        $this->productDocumentService->storeDocument(
            product: $product,
            documentType: $documentType,
            file: $request->file('document'),
            userId: (int) auth()->id()
        );

        return back()->with('success', __('Documento del producto cargado correctamente.'));
    }

    public function download(ProductDocument $document): StreamedResponse
    {
        $document->loadMissing('product');
        $this->authorize('view', $document->product);

        abort_unless(Storage::disk('local')->exists($document->file_path), 404);

        return Storage::disk('local')->download($document->file_path, $document->file_name);
    }

    public function destroy(ProductDocument $document): RedirectResponse
    {
        $document->loadMissing('product');
        $this->authorize('update', $document->product);

        $document->delete();

        return back()->with('success', __('Documento del producto eliminado correctamente.'));
    }
}
