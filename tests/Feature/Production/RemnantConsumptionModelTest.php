<?php

declare(strict_types=1);

use App\Enums\RemnantStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\RemnantConsumption;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galon', 'symbol' => 'gal']);

    $product = Product::create([
        'code' => 'P-CONSUME-'.uniqid(),
        'name' => 'Test Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'cif_percentage' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => User::factory()->create()->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Bodega Test',
        'city' => 'Cali',
        'type' => 'factory',
    ]);

    $sourceOrder = ProductionOrder::create([
        'order_number' => 'OP-SOURCE-'.uniqid(),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'created_by' => User::factory()->create()->id,
        'planned_date' => now(),
    ]);

    $this->user = User::factory()->create();

    $this->remnant = ProductionRemnant::create([
        'source_order_id' => $sourceOrder->id,
        'product_id' => $product->id,
        'warehouse_id' => $warehouse->id,
        'original_quantity_gallons' => 10,
        'available_quantity_gallons' => 10,
        'original_quantity_kg' => 50,
        'available_quantity_kg' => 50,
        'density_kg_per_gallon' => 5,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);

    $this->targetOrder = ProductionOrder::create([
        'order_number' => 'OP-TARGET-'.uniqid(),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 100,
        'created_by' => User::factory()->create()->id,
        'planned_date' => now(),
    ]);

    $this->consumption = RemnantConsumption::create([
        'remnant_id' => $this->remnant->id,
        'target_order_id' => $this->targetOrder->id,
        'quantity_gallons' => 5,
        'quantity_kg' => 25,
        'consumed_by' => $this->user->id,
        'consumed_at' => now(),
        'notes' => 'Test consumption',
    ]);
});

it('can create a remnant consumption', function () {
    expect($this->consumption->id)->toBeInt()
        ->and((float) $this->consumption->quantity_gallons)->toBe(5.0)
        ->and((float) $this->consumption->quantity_kg)->toBe(25.0);
});

it('belongs to a remnant', function () {
    expect($this->consumption->remnant)->toBeInstanceOf(ProductionRemnant::class)
        ->and($this->consumption->remnant->id)->toBe($this->remnant->id);
});

it('belongs to a target order', function () {
    expect($this->consumption->targetOrder)->toBeInstanceOf(ProductionOrder::class)
        ->and($this->consumption->targetOrder->id)->toBe($this->targetOrder->id);
});

it('target order can be null', function () {
    $consumption = RemnantConsumption::create([
        'remnant_id' => $this->remnant->id,
        'target_order_id' => null,
        'quantity_gallons' => 3,
        'quantity_kg' => 15,
        'consumed_by' => $this->user->id,
        'consumed_at' => now(),
    ]);

    expect($consumption->targetOrder)->toBeNull();
});

it('belongs to consumed by user', function () {
    expect($this->consumption->consumedBy)->toBeInstanceOf(User::class)
        ->and($this->consumption->consumedBy->id)->toBe($this->user->id);
});

it('decimal fields are cast correctly', function () {
    $fresh = $this->consumption->fresh();

    expect((float) $fresh->quantity_gallons)->toBe(5.0)
        ->and((float) $fresh->quantity_kg)->toBe(25.0);
});

it('can have notes', function () {
    expect($this->consumption->notes)->toBe('Test consumption');
});
