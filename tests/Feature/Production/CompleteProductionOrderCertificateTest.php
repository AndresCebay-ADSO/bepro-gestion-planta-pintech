<?php

declare(strict_types=1);

use App\Enums\QrDocumentType;
use App\Jobs\GenerateQualityInspectionCertificateJob;
use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\QrDocument;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityInspectionCertificateService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'produccion']);
    Role::create(['name' => 'comercial']);
});

function createCertificateCompletionFixture(): array
{
    $unit = UnitOfMeasure::create([
        'code' => 'kg-cert',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);
    $category = ProductCategory::create(['name' => 'Pinturas Certificado']);
    $user = User::create([
        'name' => 'Quality Analyst',
        'email' => 'quality@example.com',
        'password' => Hash::make('password'),
    ]);
    $user->assignRole('produccion');

    $product = Product::create([
        'code' => 'PNT-CERT-01',
        'name' => 'Pintura Certificada',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'profit_margin' => 20,
        'current_price' => 12,
        'price_threshold' => 5,
        'quality_viscosity_lower' => 90,
        'quality_viscosity_upper' => 110,
        'quality_fineness_lower' => 6,
        'quality_fineness_upper' => 8,
        'quality_solids_lower' => 45,
        'quality_solids_upper' => 55,
    ]);
    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $user->id,
    ]);
    $warehouse = Warehouse::create([
        'name' => 'Planta Calidad',
        'city' => 'Cali',
        'type' => 'factory',
    ]);
    $rawMaterial = RawMaterial::create([
        'code' => 'RM-CERT-01',
        'unit_of_measure_id' => $unit->id,
        'current_price' => 3,
        'minimum_stock' => 0,
        'tracks_inventory' => true,
        'is_active' => true,
    ]);
    $batch = InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'initial_quantity' => 100,
        'remaining_quantity' => 100,
        'unit_price' => 3,
        'entry_date' => now()->subDay()->toDateString(),
    ]);
    $order = ProductionOrder::create([
        'order_number' => 'OP-CERT-0001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $user->id,
        'spillage_quantity' => 0,
    ]);
    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'batch_id' => $batch->id,
        'raw_material_id' => $rawMaterial->id,
        'planned_quantity' => 10,
        'actual_quantity' => null,
        'unit_cost' => 3,
        'total_cost' => 30,
    ]);

    return [$user, $order, $detail];
}

test('completing an order stores quality solids and generates current certificate document', function () {
    Storage::fake('local');
    Queue::fake();
    [$user, $order, $detail] = createCertificateCompletionFixture();

    $this->actingAs($user)->post(route('production-orders.start', $order));

    $response = $this->actingAs($user)->post(route('production-orders.complete', $order), [
        'viscosity_ku' => 100,
        'grinding_hg' => 7,
        'quality_solids' => 50,
        'responsible_name' => 'Analista Calidad',
        'ingredients' => [
            [
                'id' => $detail->id,
                'actual_quantity' => 10,
            ],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect(route('production-orders.show', $order));

    $order->refresh();
    expect((float) $order->quality_solids)->toBe(50.0);

    Queue::assertPushed(GenerateQualityInspectionCertificateJob::class, function ($job) use ($order) {
        return $job->order->id === $order->id;
    });
});

test('regenerating a certificate keeps only one current certificate', function () {
    Storage::fake('local');
    [$user, $order] = createCertificateCompletionFixture();
    $service = app(QualityInspectionCertificateService::class);

    $order->update([
        'status' => 'completed',
        'completion_date' => now(),
        'viscosity_ku' => 100,
        'grinding_hg' => 7,
        'quality_solids' => 50,
        'responsible_name' => 'Analista Calidad',
    ]);

    $first = $service->generateForCompletedOrder($order->refresh(), $user->id);
    $second = $service->generateForCompletedOrder($order->refresh(), $user->id);

    expect($first->refresh()->is_current)->toBeFalse()
        ->and($second->version)->toBe(2)
        ->and(QrDocument::query()
            ->where('qr_code_id', $order->qrCode->id)
            ->where('document_type', QrDocumentType::QualityCertificate->value)
            ->where('is_current', true)
            ->count())->toBe(1);
});
