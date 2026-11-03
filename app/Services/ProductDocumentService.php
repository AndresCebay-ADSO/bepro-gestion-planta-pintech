<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QrDocumentType;
use App\Models\Product;
use App\Models\ProductDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class ProductDocumentService
{
    /** Largo del nombre del producto en el archivo guardado: deja margen bajo los 255 bytes de la ruta. */
    private const STORED_SLUG_LENGTH = 80;

    public function __construct(
        private readonly VersionedDocumentStore $documentStore,
    ) {}

    public function storeDocument(
        Product $product,
        QrDocumentType $documentType,
        UploadedFile $file,
        int $userId
    ): ProductDocument {
        $fileName = $file->getClientOriginalName();
        // El slug se recorta: con el nombre completo, el archivo pasaba de los 255 bytes que admite el sistema de
        // archivos y fallaba al guardar (PRD-04). El nombre original queda en `file_name`.
        $slug = Str::limit(Str::slug(pathinfo($fileName, PATHINFO_FILENAME)), self::STORED_SLUG_LENGTH, '');
        $extension = $file->getClientOriginalExtension();

        /** @var ProductDocument */
        return $this->documentStore->store(
            owner: $product,
            relation: 'productDocuments',
            type: $documentType,
            content: $file->getContent(),
            fileName: $fileName,
            pathFor: fn (int $version): string => "product-documents/{$product->id}/{$slug}-v{$version}-".Str::ulid().".{$extension}",
            userId: $userId,
        );
    }
}
