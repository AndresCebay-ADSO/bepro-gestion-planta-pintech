<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductDocument;
use App\Models\ProductionOrder;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'produccion']);
    Role::create(['name' => 'comercial']);
});

function createPublicQrFixture(): array
{
    Storage::fake('local');

    $unit = UnitOfMeasure::create([
        'code' => 'gal-public',
        'name' => 'Galón',
        'symbol' => 'gal',
    ]);
    $category = ProductCategory::create(['name' => 'Pinturas Públicas']);
    $user = User::create([
        'name' => 'Public QR User',
        'email' => 'public-qr@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('produccion');
    $product = Product::create([
        'code' => 'PT-PUBLIC',
        'name' => 'Pintura Pública',
        'description' => 'Producto visible en QR.',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'profit_margin' => 20,
        'price_threshold' => 5,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $warehouse = Warehouse::create([
        'name' => 'Planta QR',
        'city' => 'Cali',
        'type' => 'factory',
    ]);
    $order = ProductionOrder::create([
        'order_number' => 'OP-PUBLIC-0001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'completed',
        'planned_date' => now(),
        'completion_date' => now(),
        'created_by' => $user->id,
        'spillage_quantity' => 0,
    ]);
    $qrCode = QrCode::create([
        'product_id' => $product->id,
        'production_order_id' => $order->id,
        'token' => 'public-token',
        'url' => route('qr.public.show', 'public-token'),
        'is_active' => true,
        'created_by' => $user->id,
    ]);

    Storage::disk('local')->put('product-documents/safety.pdf', '%PDF-1.4 safety');
    Storage::disk('local')->put('quality-certificates/cert.pdf', '%PDF-1.4 cert');

    $productDocument = ProductDocument::create([
        'product_id' => $product->id,
        'document_type' => QrDocumentType::SafetyDataSheet,
        'file_name' => 'Hoja de Seguridad.pdf',
        'file_path' => 'product-documents/safety.pdf',
        'file_size' => 512000,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $user->id,
    ]);
    $certificate = QrDocument::create([
        'qr_code_id' => $qrCode->id,
        'document_type' => QrDocumentType::QualityCertificate,
        'file_name' => 'Certificado de Calidad.pdf',
        'file_path' => 'quality-certificates/cert.pdf',
        'file_size' => 90000,
        'mime_type' => 'application/pdf',
        'version' => 1,
        'is_current' => true,
        'uploaded_by' => $user->id,
    ]);

    return [$qrCode, $productDocument, $certificate];
}

test('public QR landing does not require authentication and shows available documents', function () {
    [$qrCode] = createPublicQrFixture();

    $this->get(route('qr.public.show', $qrCode->token))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Public/QrLanding/Show')
            ->where('product.name', 'Pintura Pública')
            ->where('lot.number', 'OP-PUBLIC-0001')
            ->has('documents', 2));
});

test('public downloads only allow documents associated with the scanned token', function () {
    [$qrCode, $productDocument, $certificate] = createPublicQrFixture();

    $this->get(route('qr.public.documents.download', [$qrCode->token, $certificate]))
        ->assertSuccessful();

    $this->get(route('qr.public.product-documents.download', [$qrCode->token, $productDocument]))
        ->assertSuccessful();

    $order = $qrCode->productionOrder;
    $otherOrder = ProductionOrder::create([
        'order_number' => 'OP-PUBLIC-0002',
        'product_id' => $order->product_id,
        'formula_id' => $order->formula_id,
        'warehouse_id' => $order->warehouse_id,
        'quantity' => 10,
        'status' => 'completed',
        'planned_date' => now(),
        'completion_date' => now(),
        'created_by' => $order->created_by,
        'spillage_quantity' => 0,
    ]);
    $otherQrCode = QrCode::create([
        'product_id' => $order->product_id,
        'production_order_id' => $otherOrder->id,
        'token' => 'other-public-token',
        'url' => route('qr.public.show', 'other-public-token'),
        'is_active' => true,
        'created_by' => $order->created_by,
    ]);

    $this->get(route('qr.public.documents.download', [$otherQrCode->token, $certificate]))
        ->assertNotFound();
});

test('inactive or unknown QR token returns not found', function () {
    [$qrCode] = createPublicQrFixture();
    $qrCode->update(['is_active' => false]);

    $this->get(route('qr.public.show', $qrCode->token))->assertNotFound();
    $this->get(route('qr.public.show', 'missing-token'))->assertNotFound();
});

test('qr image endpoint returns a png for a valid active token', function () {
    [$qrCode] = createPublicQrFixture();

    $response = $this->get(route('qr.public.image', $qrCode->token));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'image/png');
    expect($response->getContent())->not->toBeEmpty();
});

test('qr image endpoint returns 404 for inactive token', function () {
    [$qrCode] = createPublicQrFixture();
    $qrCode->update(['is_active' => false]);

    $this->get(route('qr.public.image', $qrCode->token))->assertNotFound();
});

test('qr image endpoint returns 404 for unknown token', function () {
    $this->get(route('qr.public.image', 'nonexistent-token'))->assertNotFound();
});
