<?php

declare(strict_types=1);

use App\Enums\RemnantStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galon', 'symbol' => 'gal']);

    $this->product = Product::create([
        'code' => 'P-REMNANT-'.uniqid(),
        'name' => 'Test Product',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'cif_percentage' => 50,
        'current_price' => 15,
        'price_threshold' => 5,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => User::factory()->create()->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Bodega Test',
        'city' => 'Cali',
        'type' => 'factory',
    ]);

    $this->sourceOrder = ProductionOrder::create([
        'order_number' => 'OP-SOURCE-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'created_by' => User::factory()->create()->id,
        'planned_date' => now(),
    ]);

    $this->user = User::factory()->create();

    $this->remnant = ProductionRemnant::create([
        'source_order_id' => $this->sourceOrder->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 10,
        'available_quantity_gallons' => 10,
        'original_quantity_kg' => 50,
        'available_quantity_kg' => 50,
        'density_kg_per_gallon' => 5,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);
});

it('can create a remnant', function () {
    expect($this->remnant->id)->toBeInt()
        ->and((float) $this->remnant->original_quantity_gallons)->toBe(10.0);
});

it('belongs to source order', function () {
    expect($this->remnant->sourceOrder)->toBeInstanceOf(ProductionOrder::class)
        ->and($this->remnant->sourceOrder->id)->toBe($this->sourceOrder->id);
});

it('belongs to product', function () {
    expect($this->remnant->product)->toBeInstanceOf(Product::class)
        ->and($this->remnant->product->id)->toBe($this->product->id);
});

it('belongs to warehouse', function () {
    expect($this->remnant->warehouse)->toBeInstanceOf(Warehouse::class)
        ->and($this->remnant->warehouse->id)->toBe($this->warehouse->id);
});

it('belongs to created by', function () {
    expect($this->remnant->createdBy)->toBeInstanceOf(User::class)
        ->and($this->remnant->createdBy->id)->toBe($this->user->id);
});

it('has many consumptions', function () {
    expect($this->remnant->consumptions)->toBeInstanceOf(Collection::class)
        ->and($this->remnant->consumptions)->toHaveCount(0);
});

it('casts status to RemnantStatus enum', function () {
    expect($this->remnant->status)->toBe(RemnantStatus::Available);
});

it('remnant status label returns correct translation', function () {
    expect(RemnantStatus::Available->label())->toBe('Disponible')
        ->and(RemnantStatus::PartiallyConsumed->label())->toBe('Parcialmente consumido')
        ->and(RemnantStatus::Consumed->label())->toBe('Consumido');
});

it('scope available returns only remnants with stock', function () {
    $order2 = ProductionOrder::create([
        'order_number' => 'OP-SCOPE-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'created_by' => $this->user->id,
        'planned_date' => now(),
    ]);

    ProductionRemnant::create([
        'source_order_id' => $order2->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 5,
        'available_quantity_gallons' => 5,
        'original_quantity_kg' => 25,
        'available_quantity_kg' => 25,
        'density_kg_per_gallon' => 5,
        'status' => RemnantStatus::PartiallyConsumed,
        'created_by' => $this->user->id,
    ]);

    $order3 = ProductionOrder::create([
        'order_number' => 'OP-SCOPE-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'created_by' => $this->user->id,
        'planned_date' => now(),
    ]);

    ProductionRemnant::create([
        'source_order_id' => $order3->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 3,
        'available_quantity_gallons' => 0,
        'original_quantity_kg' => 15,
        'available_quantity_kg' => 0,
        'density_kg_per_gallon' => 5,
        'status' => RemnantStatus::Consumed,
        'created_by' => $this->user->id,
    ]);

    $available = ProductionRemnant::available()->get();

    expect($available)->toHaveCount(2)
        ->and($available->pluck('status')->map(fn ($s) => $s->value)->toArray())
        ->toContain('available', 'partially_consumed')
        ->not->toContain('consumed');
});

it('decimal fields are cast correctly', function () {
    $decimalOrder = ProductionOrder::create([
        'order_number' => 'OP-DECIMAL-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'created_by' => $this->user->id,
        'planned_date' => now(),
    ]);

    $remnant = ProductionRemnant::create([
        'source_order_id' => $decimalOrder->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 15.5,
        'available_quantity_gallons' => 10.25,
        'original_quantity_kg' => 77.5,
        'available_quantity_kg' => 51.25,
        'density_kg_per_gallon' => 5.0,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);

    $fresh = $remnant->fresh();

    expect((float) $fresh->original_quantity_gallons)->toBe(15.5)
        ->and((float) $fresh->available_quantity_gallons)->toBe(10.25)
        ->and((float) $fresh->original_quantity_kg)->toBe(77.5)
        ->and((float) $fresh->available_quantity_kg)->toBe(51.25)
        ->and((float) $fresh->density_kg_per_gallon)->toBe(5.0);
});

it('factory creates a remnant with default values', function () {
    $factoryOrder = ProductionOrder::create([
        'order_number' => 'OP-FACTORY-'.uniqid(),
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'created_by' => $this->user->id,
        'planned_date' => now(),
    ]);

    $remnant = ProductionRemnant::factory()->create([
        'source_order_id' => $factoryOrder->id,
        'product_id' => $this->product->id,
        'warehouse_id' => $this->warehouse->id,
        'created_by' => $this->user->id,
    ]);

    expect($remnant->id)->toBeInt()
        ->and($remnant->status)->toBe(RemnantStatus::Available);
});
