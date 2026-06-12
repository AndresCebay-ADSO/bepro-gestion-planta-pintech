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
        'code' => 'P-CERT-UNIT',
        'name' => 'Producto Cert Unit',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unitOfMeasure->id,
        'current_cost' => 10,
        'profit_margin' => 20,
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

test('it falls back to order_number for quality certificate lot when lot_number is null', function () {
    $order = ProductionOrder::create([
        'order_number' => 'OP-2026-0001',
        'lot_number' => null,
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $payload = app(QualityInspectionCertificateService::class)->buildPayload($order);

    expect($payload['lot'])->toBe('OP-2026-0001')
        ->and($payload['certificate_number'])->toBe('CC-OP-2026-0001');
});
