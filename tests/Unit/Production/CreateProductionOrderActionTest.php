<?php

declare(strict_types=1);

use App\Actions\Production\CreateProductionOrderAction;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $unitOfMeasure = UnitOfMeasure::create([
        'code' => 'L-ACTION',
        'name' => 'Litro Action',
        'symbol' => 'L',
    ]);

    $category = ProductCategory::create(['name' => 'Pinturas Action']);

    $this->product = Product::create([
        'code' => 'P-ACTION',
        'name' => 'Producto Action',
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
        'name' => 'Planta Action',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('it starts the annual production order sequence at one', function () {
    Carbon::setTestNow('2026-03-10 09:00:00');

    $order = createActionProductionOrder($this);

    expect($order->order_number)->toBe('OP-2026-0001');
});

test('it increments the current year production order sequence', function () {
    Carbon::setTestNow('2026-03-10 09:00:00');
    createExistingProductionOrder($this, 'OP-2026-0007');

    $order = createActionProductionOrder($this);

    expect($order->order_number)->toBe('OP-2026-0008');
});

test('it restarts the production order sequence every year', function () {
    Carbon::setTestNow('2027-01-01 08:00:00');
    createExistingProductionOrder($this, 'OP-2026-1200');

    $order = createActionProductionOrder($this);

    expect($order->order_number)->toBe('OP-2027-0001');
});

test('it uses numeric order when production order sequence exceeds four digits', function () {
    Carbon::setTestNow('2026-12-01 09:00:00');
    createExistingProductionOrder($this, 'OP-2026-9999');
    createExistingProductionOrder($this, 'OP-2026-10000');

    $order = createActionProductionOrder($this);

    expect($order->order_number)->toBe('OP-2026-10001');
});

function createActionProductionOrder(object $context): ProductionOrder
{
    return app(CreateProductionOrderAction::class)->execute([
        'product_id' => $context->product->id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
    ], $context->user->id);
}

function createExistingProductionOrder(object $context, string $orderNumber): ProductionOrder
{
    return ProductionOrder::create([
        'order_number' => $orderNumber,
        'product_id' => $context->product->id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $context->user->id,
    ]);
}
