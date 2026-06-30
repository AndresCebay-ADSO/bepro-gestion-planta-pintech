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
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galon', 'symbol' => 'gal']);

    $this->product = Product::create([
        'code' => 'P-REMNANT-'.uniqid(),
        'name' => 'Test Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->material = RawMaterial::create([
        'code' => 'RM-REMNANT-'.uniqid(),
        'unit_of_measure_id' => $uom->id,
        'current_price' => 5000,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 0.5,
        'unit_of_measure_id' => $uom->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Planta Test',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->batch = InventoryBatch::create([
        'raw_material_id' => $this->material->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $this->order = ProductionOrder::create([
        'order_number' => 'OP-REMNANT-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::InProgress->value,
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);

    $this->detail = ProductionOrderDetail::create([
        'production_order_id' => $this->order->id,
        'raw_material_id' => $this->material->id,
        'batch_id' => $this->batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);
});

function completePayload(object $test, array $overrides = []): array
{
    return array_merge([
        'actual_yield_quantity' => 100,
        'density_kg_per_gallon' => 5,
        'remnant_quantity_gallons' => null,
        'remnant_notes' => null,
        'ingredients' => [
            ['id' => $test->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ], $overrides);
}

it('requires density when completing', function () {
    $payload = completePayload($this);
    unset($payload['density_kg_per_gallon']);

    $this->post(route('production-orders.complete', $this->order), $payload)
        ->assertSessionHasErrors(['density_kg_per_gallon']);
});

it('saves density on the order when completing', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 4.5,
    ]))->assertRedirect();

    $this->order->refresh();

    expect((float) $this->order->density_kg_per_gallon)->toBe(4.5);
});

it('does not create a remnant when no surplus gallons reported', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this))
        ->assertRedirect();

    expect(ProductionRemnant::count())->toBe(0);
});

it('creates a remnant when surplus gallons are reported', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_gallons' => 8,
        'remnant_notes' => 'Quedó en el tanque',
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    expect($remnant)->not->toBeNull()
        ->and($remnant->source_order_id)->toBe($this->order->id)
        ->and($remnant->product_id)->toBe($this->product->id)
        ->and($remnant->warehouse_id)->toBe($this->warehouse->id)
        ->and((float) $remnant->original_quantity_gallons)->toBe(8.0)
        ->and((float) $remnant->available_quantity_gallons)->toBe(8.0)
        ->and((float) $remnant->original_quantity_kg)->toBe(40.0)
        ->and((float) $remnant->available_quantity_kg)->toBe(40.0)
        ->and((float) $remnant->density_kg_per_gallon)->toBe(5.0)
        ->and($remnant->status)->toBe(RemnantStatus::Available)
        ->and($remnant->notes)->toBe('Quedó en el tanque')
        ->and($remnant->created_by)->toBe($this->user->id);
});

it('calculates remnant kg correctly using density', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 3.785,
        'remnant_quantity_gallons' => 5.5,
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    expect((float) $remnant->original_quantity_gallons)->toBe(5.5)
        ->and((float) $remnant->original_quantity_kg)->toBe(20.8175)
        ->and((float) $remnant->density_kg_per_gallon)->toBe(3.785);
});

it('assigns cost per gallon to the remnant when cost is calculated', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_gallons' => 5,
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    expect($remnant->cost_per_gallon)->not->toBeNull()
        ->and((float) $remnant->cost_per_gallon)->toBeGreaterThan(0);
});
