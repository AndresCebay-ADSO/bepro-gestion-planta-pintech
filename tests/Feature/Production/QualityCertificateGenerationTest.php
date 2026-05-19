<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\QrCode;
use App\Models\QrDocument;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityInspectionCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($this->user);

    $this->factory = Warehouse::create([
        'id' => 1,
        'name' => 'Planta Cali',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'Litro', 'symbol' => 'L']);

    $this->product = Product::create([
        'code' => 'P-CERT',
        'name' => 'Pintura Certificado',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
        'quality_viscosity_lower' => 90,
        'quality_viscosity_upper' => 120,
        'quality_fineness_lower' => 5,
        'quality_fineness_upper' => 8,
        'quality_solids_lower' => 40,
        'quality_solids_upper' => 60,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->material = RawMaterial::create([
        'code' => 'RM-CERT',
        'unit_of_measure_id' => $uom->id,
        'current_price' => 5,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 0.5,
        'unit_of_measure_id' => $uom->id,
    ]);
});

test('completing order generates qr code and quality certificate', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-CERT-0001',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();

    $order->refresh();

    // quality_solids se guarda en la orden
    expect((float) $order->quality_solids)->toBe(52.50);

    // Se genera un QR code para la orden
    $qrCode = QrCode::where('production_order_id', $order->id)->first();
    expect($qrCode)->not->toBeNull()
        ->and($qrCode->product_id)->toBe($this->product->id)
        ->and($qrCode->is_active)->toBeTrue()
        ->and($qrCode->token)->toHaveLength(40);

    // Se genera un documento certificado de calidad
    $certificate = QrDocument::where('qr_code_id', $qrCode->id)
        ->where('document_type', QrDocumentType::CertificadoCalidad->value)
        ->where('is_current', true)
        ->first();

    expect($certificate)->not->toBeNull()
        ->and($certificate->version)->toBe(1)
        ->and($certificate->mime_type)->toBe('application/pdf')
        ->and($certificate->file_size)->toBeGreaterThan(0);

    // El PDF se guardó en storage
    Storage::disk('local')->assertExists($certificate->file_path);
});

test('completing order saves quality solids alongside viscosity and grinding', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-SOLIDS-0001',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'viscosity_ku' => 110,
        'grinding_hg' => 6.5,
        'quality_solids' => 48.3,
        'responsible_name' => 'Operario Test',
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ])->assertRedirect();

    $order->refresh();
    expect((float) $order->viscosity_ku)->toBe(110.00)
        ->and((float) $order->grinding_hg)->toBe(6.50)
        ->and((float) $order->quality_solids)->toBe(48.30);
});

test('regenerating certificate does not duplicate current document', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-REGEN-0001',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    // Completar la orden (genera certificado v1)
    $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'responsible_name' => 'QC 1',
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ])->assertRedirect();

    $order->refresh();
    $qrCode = QrCode::where('production_order_id', $order->id)->firstOrFail();

    // Regenerar manualmente el certificado (simula regeneración)
    $service = app(QualityInspectionCertificateService::class);
    $service->generateForCompletedOrder($order, $this->user->id);

    // Debe haber 2 documentos totales pero solo 1 current
    $allCerts = QrDocument::where('qr_code_id', $qrCode->id)
        ->where('document_type', QrDocumentType::CertificadoCalidad->value)
        ->get();

    $currentCerts = $allCerts->where('is_current', true);

    expect($allCerts)->toHaveCount(2)
        ->and($currentCerts)->toHaveCount(1)
        ->and($currentCerts->first()->version)->toBe(2);

    // Solo 1 QR code por orden (reutiliza)
    expect(QrCode::where('production_order_id', $order->id)->count())->toBe(1);
});
