<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Enums\SystemRole;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductDocumentService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

function createProductDocumentFixture(): array
{
    $unit = UnitOfMeasure::create([
        'code' => 'gal-doc',
        'name' => 'Galón',
        'symbol' => 'gal',
    ]);
    $category = ProductCategory::create(['name' => 'Pinturas Docs']);
    $user = User::create([
        'name' => 'Product Document User',
        'email' => 'product-docs@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('admin');
    $product = Product::create([
        'name' => 'Pintura con Docs',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'cif_percentage' => 20,
        'price_threshold' => 5,
    ]);

    return [$user, $product];
}

test('authorized users can upload and version product PDF documents', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    $this->actingAs($user)->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::SafetyDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent('seguridad.pdf', "%PDF-1.4\nfirst"),
    ])->assertRedirect();

    $this->actingAs($user)->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::SafetyDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent('seguridad-v2.pdf', "%PDF-1.4\nsecond"),
    ])->assertRedirect();

    expect(ProductDocument::query()->where('product_id', $product->id)->count())->toBe(2)
        ->and(ProductDocument::query()->where('product_id', $product->id)->where('is_current', true)->count())->toBe(1)
        ->and(ProductDocument::query()->where('product_id', $product->id)->where('is_current', true)->first()->version)->toBe(2);
});

test('authorized users can preview product PDF documents inline with nosniff header', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    Storage::disk('local')->put("product-documents/{$product->id}/ficha.pdf", "%PDF-1.4\ncontent");

    $document = ProductDocument::create([
        'product_id' => $product->id,
        'document_type' => QrDocumentType::TechnicalDataSheet,
        'file_name' => 'Ficha Técnica.pdf',
        'file_path' => "product-documents/{$product->id}/ficha.pdf",
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $user->id,
    ]);

    $response = $this->actingAs($user)
        ->get(route('products.documents.download', $document));

    $response->assertSuccessful()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect($response->headers->get('Content-Disposition'))
        ->toStartWith('inline;')
        ->toContain('Ficha Tecnica.pdf');
});

test('product document upload validates PDF files', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    $this->actingAs($user)->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::SafetyDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent('seguridad.txt', 'not a pdf'),
    ])->assertSessionHasErrors('document');
});

test('guests cannot manage product documents', function () {
    [$user, $product] = createProductDocumentFixture();

    $this->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::SafetyDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent('seguridad.pdf', "%PDF-1.4\ncontent"),
    ])->assertRedirect('/login');

    $document = ProductDocument::create([
        'product_id' => $product->id,
        'document_type' => QrDocumentType::TechnicalDataSheet,
        'file_name' => 'Ficha.pdf',
        'file_path' => 'product-documents/ficha.pdf',
        'file_size' => 100,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $user->id,
    ]);

    $this->delete(route('products.documents.destroy', $document))->assertRedirect('/login');
});

test('it deletes physical file from storage if transaction fails', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    // Force an invalid user ID or database error to trigger transaction failure during service call
    $service = new ProductDocumentService;

    $file = UploadedFile::fake()->createWithContent('seguridad.pdf', "%PDF-1.4\ncontent");

    try {
        // userId 999999 will fail due to restrictOnDelete / foreign key constraint on users table
        $service->storeDocument($product, QrDocumentType::SafetyDataSheet, $file, 999999);
        $this->fail('Expected transaction to fail due to foreign key constraint on users table');
    } catch (Throwable $e) {
        // Assert transaction rolled back
        expect(ProductDocument::query()->where('product_id', $product->id)->count())->toBe(0);

        // Assert physical file was cleaned up from storage (no orphans)
        $files = Storage::disk('local')->allFiles("product-documents/{$product->id}");
        expect($files)->toBeEmpty();
    }
});

test('produccion cannot upload product documents', function () {
    [, $product] = createProductDocumentFixture();

    $this->actingAs(userWithRole(SystemRole::Production))
        ->post(route('products.documents.store', $product), [])
        ->assertForbidden();
});

// Auditoría 3.6, PR 10 (PRD-04): el nombre guardado era `slug(nombre)-vN-ULID.pdf` y con un nombre largo pasaba de los 255
// bytes que admite el sistema de archivos: «Failed to store document file to disk» (500).

test('a document with a long name is stored with a bounded file name', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    $this->actingAs($user)->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::TechnicalDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent(str_repeat('ficha-tecnica-', 17).'.pdf', "%PDF-1.4\nlong"),
    ])->assertSessionHasNoErrors()->assertRedirect();

    $document = ProductDocument::query()->where('product_id', $product->id)->firstOrFail();

    expect(strlen(basename($document->file_path)))->toBeLessThan(150)
        ->and(Storage::disk('local')->exists($document->file_path))->toBeTrue()
        ->and(strlen($document->file_name))->toBeGreaterThan(200);
});

test('a document whose original name does not fit the file name column is rejected with 422', function () {
    Storage::fake('local');
    [$user, $product] = createProductDocumentFixture();

    $this->actingAs($user)->post(route('products.documents.store', $product), [
        'document_type' => QrDocumentType::TechnicalDataSheet->value,
        'document' => UploadedFile::fake()->createWithContent(str_repeat('a', 300).'.pdf', "%PDF-1.4\nlong"),
    ])->assertSessionHasErrors(['document' => 'El nombre del archivo no puede pasar de 255 caracteres.']);

    expect(ProductDocument::query()->count())->toBe(0);
});
