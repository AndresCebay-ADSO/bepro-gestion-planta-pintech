<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductDocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::firstOrCreate(['name' => 'admin']);
    Role::firstOrCreate(['name' => 'produccion']);
    Role::firstOrCreate(['name' => 'comercial']);
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
    $user->assignRole('produccion');
    $product = Product::create([
        'code' => 'PNT-DOC',
        'name' => 'Pintura con Docs',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'profit_margin' => 20,
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
