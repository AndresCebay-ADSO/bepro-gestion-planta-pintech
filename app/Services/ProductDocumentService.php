<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QrDocumentType;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProductDocumentService
{
    /**
     * Stores a product document safely, preventing concurrent version race conditions
     * and ensuring no orphan files are left on disk on transaction failures.
     *
     * @throws Throwable
     */
    public function storeDocument(
        Product $product,
        QrDocumentType $documentType,
        UploadedFile $file,
        int $userId
    ): ProductDocument {
        $path = null;

        try {
            return DB::transaction(function () use ($product, $documentType, $file, $userId, &$path): ProductDocument {
                // 1. Lock the parent Product record to serialize concurrent version calculations for this product
                $lockedProduct = Product::query()->lockForUpdate()->findOrFail($product->id);

                // 2. Safely calculate the next version number
                $version = ((int) $lockedProduct->productDocuments()
                    ->where('document_type', $documentType->value)
                    ->max('version')) + 1;

                // 3. Generate a safe and unique filename
                $fileName = $file->getClientOriginalName();
                $storedName = Str::slug(pathinfo($fileName, PATHINFO_FILENAME))
                    ."-v{$version}-"
                    .Str::ulid()
                    .'.'.$file->getClientOriginalExtension();

                // 4. Store the file to local storage inside the transaction boundary
                $path = $file->storeAs("product-documents/{$lockedProduct->id}", $storedName, 'local');

                if (! $path) {
                    throw new \RuntimeException('Failed to store document file to disk.');
                }

                // 5. Deactivate previous versions of this document type for the product
                $lockedProduct->productDocuments()
                    ->where('document_type', $documentType->value)
                    ->update(['is_current' => false]);

                // 6. Create and return the new document database record
                /** @var ProductDocument */
                return $lockedProduct->productDocuments()->create([
                    'document_type' => $documentType,
                    'file_name' => $fileName,
                    'file_path' => $path,
                    'file_size' => (int) $file->getSize(),
                    'mime_type' => $file->getMimeType() ?: 'application/pdf',
                    'version' => $version,
                    'is_current' => true,
                    'uploaded_by' => $userId,
                ]);
            });
        } catch (Throwable $e) {
            // Cleanup: if file was stored on disk but transaction rolled back, delete the orphan file
            if ($path && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }

            throw $e;
        }
    }
}
