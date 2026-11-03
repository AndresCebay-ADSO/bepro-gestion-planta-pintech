<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\QualityInspectionCertificateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $unitOfMeasure = UnitOfMeasure::create([
        'code' => 'L-CERT',
        'name' => 'Litro Cert',
        'symbol' => 'L',
    ]);

    $category = ProductCategory::create(['name' => 'Pinturas Cert']);

    $this->product = Product::create([
        'name' => 'Producto Cert Unit',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unitOfMeasure->id,
        'current_cost' => 10,
        'cif_percentage' => 20,
        'current_price' => 12,
        'price_threshold' => 5,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Planta Cert Unit',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);
});

test('it uses lot_number for quality certificate lot when present', function () {
    $order = ProductionOrder::create([
        'order_number' => 'OP-2026-0001',
        'lot_number' => 1620,
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $payload = app(QualityInspectionCertificateService::class)->buildPayload($order);

    expect($payload['lot'])->toBe(1620)
        ->and($payload['certificate_number'])->toBe('CC-1620');
});

test('a numeric test appears on the certificate only if it has a limit or a measurement', function (array $limits, array $results, array $expectedTests) {
    $this->product->update($limits);
    $order = ProductionOrder::create([
        'order_number' => 'OP-2026-0002',
        'lot_number' => 1621,
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'status' => 'completed',
        'planned_date' => now(),
        'created_by' => $this->user->id,
        ...$results,
    ]);

    $tests = app(QualityInspectionCertificateService::class)->buildPayload($order)['tests'];

    expect(array_column($tests, 'name'))->toBe($expectedTests)
        ->and(array_keys($tests))->toBe(array_keys($expectedTests));
})->with([
    'sin límites ni medición: solo la apariencia' => [[], [], ['APARIENCIA']],
    'sin límites pero medida: no se calla el resultado' => [[], ['viscosity_ku' => 100], ['VISCOSIDAD', 'APARIENCIA']],
    'con mínimo y máximo, sin medición' => [
        ['quality_viscosity_lower' => 90, 'quality_viscosity_upper' => 110],
        [],
        ['VISCOSIDAD', 'APARIENCIA'],
    ],
    'con un solo límite se conserva' => [
        ['quality_fineness_lower' => 5, 'quality_solids_upper' => 60],
        [],
        ['FINURA HEGMAN', 'SÓLIDOS', 'APARIENCIA'],
    ],
]);
