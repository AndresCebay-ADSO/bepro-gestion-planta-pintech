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
use Spatie\Activitylog\Models\Activity;
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
test('it starts the lot number sequence at the configured start value', function () {
    config(['production.lot_start_number' => 1620]);

    $order = createActionProductionOrder($this);

    expect($order->lot_number)->toBe(1620);
});

test('it increments the lot number sequence historically', function () {
    config(['production.lot_start_number' => 1620]);
    createExistingProductionOrder($this, 'OP-2026-0001', 1620);

    $order = createActionProductionOrder($this);

    expect($order->lot_number)->toBe(1621);
});

test('it does not restart the lot number sequence every year', function () {
    config(['production.lot_start_number' => 1620]);
    createExistingProductionOrder($this, 'OP-2026-0001', 1620);

    // Cambiar de año
    Carbon::setTestNow('2027-01-01 08:00:00');

    $order = createActionProductionOrder($this);

    expect($order->lot_number)->toBe(1621);
});

test('it respects the configured start value even if historical records have lower numbers', function () {
    config(['production.lot_start_number' => 2000]);
    createExistingProductionOrder($this, 'OP-2026-0001', 500);

    $order = createActionProductionOrder($this);

    expect($order->lot_number)->toBe(2000);
});

// 3.4: el color que pidió el cliente se escribe a mano al crear la OP y se une al nombre del producto solo para mostrar.
test('it stores the requested color, audits it and names the product with it', function () {
    $order = app(CreateProductionOrderAction::class)->execute([
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->addDay()->toDateString(),
        'color' => 'RAL 3020',
    ], $this->user->id);

    $created = Activity::query()->where('subject_type', ProductionOrder::class)->where('subject_id', $order->id)->sole();

    expect($order->fresh()->color)->toBe('RAL 3020')
        ->and($order->productDisplayName())->toBe("{$this->product->name} RAL 3020")
        ->and($created->properties['attributes']['color'])->toBe('RAL 3020');
});

test('it leaves the color empty when none is given', function () {
    $order = createActionProductionOrder($this);

    expect($order->fresh()->color)->toBeNull()
        ->and($order->productDisplayName())->toBe($this->product->name);
});

test('it joins the product name and the color in a single place', function (?string $color, string $expected) {
    expect(ProductionOrder::nameWithColor('Esmalte rojo', $color))->toBe($expected);
})->with([
    'con color' => ['RAL 3020', 'Esmalte rojo RAL 3020'],
    'sin color' => [null, 'Esmalte rojo'],
    'color vacío' => ['', 'Esmalte rojo'],
]);

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

function createExistingProductionOrder(object $context, string $orderNumber, ?int $lotNumber = null): ProductionOrder
{
    return ProductionOrder::create([
        'order_number' => $orderNumber,
        'lot_number' => $lotNumber ?? fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $context->product->id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => 10,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $context->user->id,
    ]);
}
