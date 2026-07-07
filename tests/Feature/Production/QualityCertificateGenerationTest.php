<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Jobs\GenerateQualityInspectionCertificateJob;
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
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('local');
    Storage::fake('public');

    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);
    $this->user->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($this->user);

    Storage::disk('public')->put('signatures/test.png', 'fake-signature-content');

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
        'cif_percentage' => 0,
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

    Queue::fake();

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'quality_responsible_user_id' => $this->user->id,
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();

    $order->refresh();

    // quality_solids se guarda en la orden
    expect((float) $order->quality_solids)->toBe(52.50);

    Queue::assertPushed(GenerateQualityInspectionCertificateJob::class, function ($job) use ($order) {
        return $job->order->id === $order->id;
    });
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

    $this->post(route('production-orders.start', $order));

    $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'viscosity_ku' => 110,
        'grinding_hg' => 6.5,
        'quality_solids' => 48.3,
        'responsible_name' => 'Operario Test',
        'quality_responsible_user_id' => $this->user->id,
        'density_kg_per_gallon' => 5,
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

    // Completar la orden (no genera el certificado porque interceptamos la ejecución con el servicio)
    $order->update([
        'status' => 'completed',
        'completion_date' => now(),
        'actual_quantity' => 100,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'responsible_name' => 'QC 1',
        'quality_responsible_user_id' => $this->user->id,
    ]);

    $service = app(QualityInspectionCertificateService::class);
    $service->generateForCompletedOrder($order, $this->user->id);

    $order->refresh();
    $qrCode = QrCode::where('production_order_id', $order->id)->firstOrFail();

    // Regenerar manualmente el certificado (simula regeneración)
    $service = app(QualityInspectionCertificateService::class);
    $service->generateForCompletedOrder($order, $this->user->id);

    // Debe haber 2 documentos totales pero solo 1 current
    $allCerts = QrDocument::where('qr_code_id', $qrCode->id)
        ->where('document_type', QrDocumentType::QualityCertificate->value)
        ->get();

    $currentCerts = $allCerts->where('is_current', true);

    expect($allCerts)->toHaveCount(2)
        ->and($currentCerts)->toHaveCount(1)
        ->and($currentCerts->first()->version)->toBe(2);

    // Solo 1 QR code por orden (reutiliza)
    expect(QrCode::where('production_order_id', $order->id)->count())->toBe(1);
});

test('quality signer is required on complete', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-QS-REQ-0001',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertSessionHasErrors('quality_responsible_user_id');
});

test('quality signer must have job_title and signature', function () {
    $incompleteUser = User::factory()->create([
        'job_title' => null,
        'signature_path' => null,
    ]);
    $incompleteUser->assignRole(Role::create(['name' => 'produccion']));

    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-QS-INC-0001',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'quality_responsible_user_id' => $incompleteUser->id,
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertSessionHasErrors('quality_responsible_user_id');
});

test('quality signer must have admin or produccion role', function () {
    $operatorUser = User::factory()->create([
        'job_title' => 'Operario de Planta',
        'signature_path' => 'signatures/operator.png',
    ]);
    $operatorUser->assignRole(Role::create(['name' => 'operador']));

    Storage::disk('public')->put('signatures/operator.png', 'fake-signature-content');

    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-QS-ROL-0001',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'quality_responsible_user_id' => $operatorUser->id,
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertSessionHasErrors('quality_responsible_user_id');
});

test('quality signer must be active', function () {
    $inactiveUser = User::factory()->create([
        'is_active' => false,
        'job_title' => 'Jefe de Calidad',
        'signature_path' => 'signatures/inactive.png',
    ]);
    $inactiveUser->assignRole(Role::create(['name' => 'produccion']));

    Storage::disk('public')->put('signatures/inactive.png', 'fake-signature-content');

    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-QS-ACT-0001',
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

    $this->post(route('production-orders.start', $order));

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 98,
        'viscosity_ku' => 105,
        'grinding_hg' => 7,
        'quality_solids' => 52.5,
        'responsible_name' => 'Analista QC',
        'quality_responsible_user_id' => $inactiveUser->id,
        'density_kg_per_gallon' => 5,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertSessionHasErrors('quality_responsible_user_id');
});

test('certificate uses quality responsible user data', function () {
    $batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->factory->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-QS-DATA-0001',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->factory->id,
        'quantity' => 100,
        'status' => 'completed',
        'planned_date' => now(),
        'completion_date' => now(),
        'created_by' => $this->user->id,
        'quality_responsible_user_id' => $this->user->id,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $service = app(QualityInspectionCertificateService::class);
    $payload = $service->buildPayload($order);

    expect($payload['responsible_name'])->toBe($this->user->name)
        ->and($payload['responsible_role'])->toBe($this->user->job_title);
});
