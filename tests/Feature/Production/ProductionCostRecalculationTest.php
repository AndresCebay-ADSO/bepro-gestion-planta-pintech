<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (Role::count() === 0) {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'produccion']);
        Role::create(['name' => 'comercial']);
    }

    $this->admin = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $this->admin->assignRole('admin');
    $this->actingAs($this->admin);

    $this->unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $this->category = ProductCategory::create([
        'name' => 'Pinturas',
    ]);

    $this->product = Product::create([
        'code' => 'P-RECALC-01',
        'name' => 'Pintura Recalculo',
        'category_id' => $this->category->id,
        'unit_of_measure_id' => $this->unit->id,
        'current_cost' => null,
        'profit_margin' => 25,
        'current_price' => null,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $this->rawMaterialOne = RawMaterial::create([
        'code' => 'RM-REC-01',
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 10,
        'previous_price' => null,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->rawMaterialTwo = RawMaterial::create([
        'code' => 'RM-REC-02',
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 5,
        'previous_price' => null,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);
});

test('it recalculates and stores production cost when a formula is created', function () {
    $variantOne = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'P-RECALC-01-1GL',
        'unit_of_measure_id' => $this->unit->id,
        'component_system' => '1K',
        'presentation_value' => 1,
        'current_cost' => null,
    ]);
    $variantFive = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'P-RECALC-01-5GL',
        'unit_of_measure_id' => $this->unit->id,
        'component_system' => '1K',
        'presentation_value' => 5,
        'current_cost' => null,
    ]);

    $response = $this->post(route('formulas.store'), [
        'product_id' => $this->product->id,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialOne->id,
                'quantity' => 2,
                'unit_of_measure_id' => $this->unit->id,
            ],
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => 3,
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ]);

    $response->assertRedirect(route('formulas.index'));

    $this->assertDatabaseHas('production_costs', [
        'product_id' => $this->product->id,
        'formula_id' => Formula::query()->where('product_id', $this->product->id)->value('id'),
        'production_order_id' => null,
        'cost' => 35,
        'unit_cost' => 35,
    ]);

    $this->product->refresh();
    expect((float) $this->product->current_cost)->toBe(35.0);
    expect((float) $this->product->current_price)->toBe(43.75);

    $variantOne->refresh();
    $variantFive->refresh();
    expect((float) $variantOne->current_cost)->toBe(35.0);
    expect((float) $variantFive->current_cost)->toBe(175.0);
    expect((float) $variantOne->current_price)->toBe(43.75);
    expect((float) $variantFive->current_price)->toBe(218.75);
});

test('it recalculates production costs when a raw material price changes', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'P-RECALC-01-1GL-RAW-UPD',
        'unit_of_measure_id' => $this->unit->id,
        'component_system' => '1K',
        'presentation_value' => 1,
        'current_cost' => null,
        'current_price' => null,
    ]);

    $this->post(route('formulas.store'), [
        'product_id' => $this->product->id,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialOne->id,
                'quantity' => 2,
                'unit_of_measure_id' => $this->unit->id,
            ],
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => 3,
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ])->assertRedirect(route('formulas.index'));

    $response = $this->patch(route('raw-materials.update', $this->rawMaterialOne), [
        'code' => $this->rawMaterialOne->code,
        'category_id' => null,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 12,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('raw-materials.index'));

    $latestCost = ProductionCost::query()
        ->where('product_id', $this->product->id)
        ->whereNull('production_order_id')
        ->latest('id')
        ->first();

    expect($latestCost)->not->toBeNull();
    expect((float) $latestCost->cost)->toBe(39.0);
    expect((float) $latestCost->unit_cost)->toBe(39.0);
    expect(round((float) $latestCost->variation_percentage, 4))->toBe(11.4286);

    $this->product->refresh();
    expect((float) $this->product->current_cost)->toBe(39.0);
    expect((float) $this->product->current_price)->toBe(48.75);

    $variant->refresh();
    expect((float) $variant->current_cost)->toBe(39.0);
    expect((float) $variant->current_price)->toBe(48.75);
});

test('it keeps current price when cost variation is below threshold', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'sku' => 'P-RECALC-01-1GL-BELOW-THRESHOLD',
        'unit_of_measure_id' => $this->unit->id,
        'component_system' => '1K',
        'presentation_value' => 1,
        'current_cost' => null,
        'current_price' => null,
    ]);

    $this->post(route('formulas.store'), [
        'product_id' => $this->product->id,
        'details' => [
            [
                'raw_material_id' => $this->rawMaterialOne->id,
                'quantity' => 2,
                'unit_of_measure_id' => $this->unit->id,
            ],
            [
                'raw_material_id' => $this->rawMaterialTwo->id,
                'quantity' => 3,
                'unit_of_measure_id' => $this->unit->id,
            ],
        ],
    ])->assertRedirect(route('formulas.index'));

    $this->product->refresh();
    expect((float) $this->product->current_price)->toBe(43.75);
    $variant->refresh();
    expect((float) $variant->current_price)->toBe(43.75);

    $response = $this->patch(route('raw-materials.update', $this->rawMaterialOne), [
        'code' => $this->rawMaterialOne->code,
        'category_id' => null,
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 10.1,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $response->assertRedirect(route('raw-materials.index'));

    $this->product->refresh();
    expect((float) $this->product->current_cost)->toBe(35.2);
    expect((float) $this->product->current_price)->toBe(43.75);

    $variant->refresh();
    expect((float) $variant->current_cost)->toBe(35.2);
    expect((float) $variant->current_price)->toBe(43.75);
});
