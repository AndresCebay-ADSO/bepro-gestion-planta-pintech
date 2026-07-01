<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionRemnant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'admin']);
    Role::create(['name' => 'produccion']);
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galon', 'symbol' => 'gal']);

    $this->sourceProduct = Product::create([
        'code' => 'P-SOURCE-'.uniqid(),
        'name' => 'Source Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);

    $this->targetProduct = Product::create([
        'code' => 'P-TARGET-'.uniqid(),
        'name' => 'Target Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 12,
        'profit_margin' => 40,
        'current_price' => 16.8,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $this->sourceProduct->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $material = RawMaterial::create([
        'code' => 'RM-CONSUME-'.uniqid(),
        'unit_of_measure_id' => $uom->id,
        'current_price' => 5000,
    ]);

    FormulaDetail::create([
        'formula_id' => $formula->id,
        'raw_material_id' => $material->id,
        'quantity' => 0.5,
        'unit_of_measure_id' => $uom->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Planta Consume Test',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->batch = InventoryBatch::create([
        'raw_material_id' => $material->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    // Source order — completed, with a remnant
    $this->sourceOrder = ProductionOrder::create([
        'order_number' => 'OP-SOURCE-'.uniqid(),
        'product_id' => $this->sourceProduct->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Completed->value,
        'planned_date' => now(),
        'created_by' => $this->user->id,
        'density_kg_per_gallon' => 5,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $this->sourceOrder->id,
        'raw_material_id' => $material->id,
        'batch_id' => $this->batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    $this->remnant = ProductionRemnant::create([
        'source_order_id' => $this->sourceOrder->id,
        'product_id' => $this->sourceProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 10,
        'original_quantity_kg' => 50,
        'available_quantity_gallons' => 10,
        'available_quantity_kg' => 50,
        'density_kg_per_gallon' => 5,
        'cost_per_gallon' => 5.5,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);

    // Another source order + remnant (for FIFO testing)
    $this->sourceOrder2 = ProductionOrder::create([
        'order_number' => 'OP-SOURCE2-'.uniqid(),
        'product_id' => $this->sourceProduct->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'status' => ProductionOrderStatus::Completed->value,
        'planned_date' => now(),
        'created_by' => $this->user->id,
        'density_kg_per_gallon' => 5,
    ]);

    $this->remnant2 = ProductionRemnant::create([
        'source_order_id' => $this->sourceOrder2->id,
        'product_id' => $this->sourceProduct->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 5,
        'original_quantity_kg' => 25,
        'available_quantity_gallons' => 5,
        'available_quantity_kg' => 25,
        'density_kg_per_gallon' => 5,
        'cost_per_gallon' => 5.5,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);

    // Target order — InProgress, different product (to verify cross-product works)
    $targetFormula = Formula::create([
        'product_id' => $this->targetProduct->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    FormulaDetail::create([
        'formula_id' => $targetFormula->id,
        'raw_material_id' => $material->id,
        'quantity' => 0.3,
        'unit_of_measure_id' => $uom->id,
    ]);

    $this->targetOrder = ProductionOrder::create([
        'order_number' => 'OP-TARGET-'.uniqid(),
        'product_id' => $this->targetProduct->id,
        'formula_id' => $targetFormula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 50,
        'status' => ProductionOrderStatus::InProgress->value,
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);
});

it('consumes a remnant partially', function () {
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 4,
        'notes' => 'Consumo parcial',
    ])->assertRedirect()->assertSessionHas('success');

    $this->remnant->refresh();

    expect((float) $this->remnant->available_quantity_gallons)->toBe(6.0)
        ->and((float) $this->remnant->available_quantity_kg)->toBe(30.0)
        ->and($this->remnant->status)->toBe(RemnantStatus::PartiallyConsumed);
});

it('consumes a remnant fully', function () {
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 10,
        'notes' => 'Consumo total',
    ])->assertRedirect()->assertSessionHas('success');

    $this->remnant->refresh();

    expect((float) $this->remnant->available_quantity_gallons)->toBe(0.0)
        ->and((float) $this->remnant->available_quantity_kg)->toBe(0.0)
        ->and($this->remnant->status)->toBe(RemnantStatus::Consumed);
});

it('creates consumption record with correct data', function () {
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 4,
        'notes' => 'Consumo de prueba',
    ])->assertRedirect();

    $consumption = $this->remnant->consumptions()->first();

    expect($consumption)->not->toBeNull()
        ->and((int) $consumption->target_order_id)->toBe($this->targetOrder->id)
        ->and((float) $consumption->quantity_gallons)->toBe(4.0)
        ->and((float) $consumption->quantity_kg)->toBe(20.0)
        ->and((int) $consumption->consumed_by)->toBe($this->user->id)
        ->and($consumption->notes)->toBe('Consumo de prueba')
        ->and($consumption->consumed_at)->not->toBeNull();
});

it('allows consuming from a different product remnant', function () {
    // remnant product = sourceProduct, targetOrder product = targetProduct → should work
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 3,
    ])->assertRedirect()->assertSessionHas('success');

    expect($this->remnant->fresh()->status)->not->toBe(RemnantStatus::Available);
});

it('validates remnant must be available or partially consumed', function () {
    // Fully consume the remnant first
    $this->remnant->update([
        'available_quantity_gallons' => 0,
        'available_quantity_kg' => 0,
        'status' => RemnantStatus::Consumed,
    ]);

    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 1,
    ])->assertSessionHasErrors(['remnant_id']);
});

it('validates target order must be in progress', function () {
    $this->targetOrder->update(['status' => ProductionOrderStatus::Completed->value]);

    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 1,
    ])->assertForbidden();
});

it('validates quantity must be greater than zero', function () {
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 0,
    ])->assertSessionHasErrors(['quantity_gallons']);
});

it('validates cannot consume more than available', function () {
    $this->post(route('production-orders.consume-remnant', $this->targetOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 20,
    ])->assertSessionHasErrors(['quantity_gallons']);
});

it('validates warehouse mismatch', function () {
    $otherWarehouse = Warehouse::create([
        'name' => 'Otra Bodega',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $otherWarehouseOrder = ProductionOrder::create([
        'order_number' => 'OP-OTHER-WH-'.uniqid(),
        'product_id' => $this->targetProduct->id,
        'formula_id' => Formula::create([
            'product_id' => $this->targetProduct->id,
            'version' => 1,
            'is_active' => true,
            'created_by' => $this->user->id,
        ])->id,
        'warehouse_id' => $otherWarehouse->id,
        'quantity' => 10,
        'status' => ProductionOrderStatus::InProgress->value,
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $this->post(route('production-orders.consume-remnant', $otherWarehouseOrder), [
        'remnant_id' => $this->remnant->id,
        'quantity_gallons' => 1,
    ])->assertSessionHasErrors(['remnant_id']);
});

it('consumes in FIFO order via json endpoint', function () {
    // The availableRemnants endpoint returns older remnants first
    $response = $this->get(route('production-orders.available-remnants', $this->targetOrder))
        ->assertOk();

    $remnants = $response->json();

    expect($remnants)->toHaveCount(2)
        ->and($remnants[0]['id'])->toBe($this->remnant->id) // first created
        ->and($remnants[1]['id'])->toBe($this->remnant2->id); // second created
});

it('returns curated fields in available remnants json', function () {
    $response = $this->get(route('production-orders.available-remnants', $this->targetOrder))
        ->assertOk();

    $remnant = $response->json()[0];

    expect($remnant)->toHaveKey('id')
        ->toHaveKey('source_order_number')
        ->toHaveKey('available_quantity_gallons')
        ->toHaveKey('density_kg_per_gallon')
        ->not->toHaveKey('cost_per_gallon')
        ->not->toHaveKey('product_id')
        ->not->toHaveKey('notes');
});

it('excludes consumed remnants from json endpoint', function () {
    // Consume fully
    $this->remnant->update([
        'available_quantity_gallons' => 0,
        'available_quantity_kg' => 0,
        'status' => RemnantStatus::Consumed,
    ]);

    $response = $this->get(route('production-orders.available-remnants', $this->targetOrder))
        ->assertOk();

    $remnants = $response->json();

    expect($remnants)->toHaveCount(1)
        ->and($remnants[0]['id'])->toBe($this->remnant2->id);
});
