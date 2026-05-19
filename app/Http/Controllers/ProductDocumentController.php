<?php

namespace App\Http\Controllers;

use App\Enums\QrDocumentType;
use App\Http\Requests\Products\StoreProductDocumentRequest;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductDocumentController extends Controller
{
    public function store(StoreProductDocumentRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();
        $documentType = QrDocumentType::from($validated['document_type']);
        $file = $request->file('document');
        $version = ((int) $product->productDocuments()
            ->where('document_type', $documentType->value)
            ->max('version')) + 1;
        $fileName = $file->getClientOriginalName();
        $storedName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME))
            ."-v{$version}-"
            .Str::ulid()
            .'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs("product-documents/{$product->id}", $storedName, 'local');

        DB::transaction(function () use ($product, $documentType, $file, $fileName, $path, $version): void {
            $product->productDocuments()
                ->where('document_type', $documentType->value)
                ->update(['is_current' => false]);

            $product->productDocuments()->create([
                'document_type' => $documentType,
                'file_name' => $fileName,
                'file_path' => $path,
                'file_size' => (int) $file->getSize(),
                'mime_type' => $file->getMimeType() ?: 'application/pdf',
                'version' => $version,
                'is_current' => true,
                'uploaded_by' => (int) auth()->id(),
            ]);
        });

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
