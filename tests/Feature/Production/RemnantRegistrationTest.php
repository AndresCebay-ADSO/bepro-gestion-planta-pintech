<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Models\FinishedInventoryMovement;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
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
        'cif_percentage' => 50,
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
    $defaults = [
        'actual_yield_quantity' => 100,
        'density_kg_per_gallon' => 5,
        'remnant_quantity_gallons' => null,
        'remnant_notes' => null,
        'ingredients' => [
            ['id' => $test->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ];

    $payload = array_merge($defaults, $overrides);

    // When there is a remnant but no packaging, and actual_yield_quantity was not explicitly overridden,
    // set it to match the remnant so validation passes by default.
    $hasPackaging = is_array($payload['packaging']) && $payload['packaging'] !== [];
    $remnantGallons = (float) ($payload['remnant_quantity_gallons'] ?? 0);
    $explicitYieldOverridden = array_key_exists('actual_yield_quantity', $overrides);
    if (! $hasPackaging && $remnantGallons > 0 && ! $explicitYieldOverridden) {
        $payload['actual_yield_quantity'] = $remnantGallons;
    }

    return $payload;
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

it('validates actual yield must include remnant when no packaging', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_gallons' => 5,
        'actual_yield_quantity' => 3, // Wrong: should be 5 to match remnant
    ]))->assertSessionHasErrors(['actual_yield_quantity']);
});

it('distributes bulk cost across packaging and remnant', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-GALON',
        'name' => 'Galón',
        'unit_of_measure_id' => UnitOfMeasure::first()->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $packPlan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $this->post(route('production-orders.start', $this->order));

    $this->post(route('production-orders.complete', $this->order), [
        'actual_yield_quantity' => 25, // 20 gal envasados + 5 gal saldo
        'density_kg_per_gallon' => 5,
        'remnant_quantity_gallons' => 5,
        'ingredients' => [
            ['id' => $this->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $packPlan->id, 'actual_units' => 20],
        ],
    ])->assertRedirect();

    $movement = FinishedInventoryMovement::where('production_order_id', $this->order->id)->first();
    $remnant = ProductionRemnant::first();

    // Total bulk cost: 50 * 5 = 250
    // Total to distribute: 20 (envasado) + 5 (saldo) = 25 gal
    // Costo granel por gal: 250 / 25 = 10.00
    // Costo por gal con CIF (50%): 10.00 * 1.50 = 15.00
    // El envasado usa costo granel (10.00), el saldo usa costo con CIF (15.00)

    expect($movement)->not->toBeNull();
    expect(round((float) $movement->cost_price, 2))->toBe(10.00);
    expect($remnant)->not->toBeNull();
    expect(round((float) $remnant->cost_per_gallon, 2))->toBe(15.00);
});

it('assigns all bulk cost to remnant when there is no packaging', function () {
    $this->post(route('production-orders.start', $this->order));

    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_gallons' => 5,
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    // Total bulk cost: 50 * 5 = 250
    // Solo saldo: 5 gal
    // Costo granel por gal: 250 / 5 = 50.00
    // Costo por gal con CIF (50%): 50.00 * 1.50 = 75.00

    expect($remnant)->not->toBeNull();
    expect(round((float) $remnant->cost_per_gallon, 2))->toBe(75.00);
});

it('preview costs distributes correctly when remnant is present', function () {
    $variant = ProductVariant::create([
        'product_id' => $this->product->id,
        'code' => 'VAR-GALON',
        'name' => 'Galón',
        'unit_of_measure_id' => UnitOfMeasure::first()->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $packPlan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);

    $this->post(route('production-orders.start', $this->order));

    $response = $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [
            ['id' => $this->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [
            ['id' => $packPlan->id, 'actual_units' => 20],
        ],
        'remnant_quantity_gallons' => 5,
    ]);

    $response->assertOk();
    $data = $response->json();

    // Total bulk cost: 50 * 5 = 250
    // Total to distribute: 20 (envasado) + 5 (saldo) = 25 gal
    // Costo granel por gal: 250 / 25 = 10.00
    // Costo por gal con CIF (50%): 10.00 * 1.50 = 15.00
    // Costo saldo con CIF: 15.00 * 5 = 75.00

    $packaging = $data['packaging'];
    expect($packaging)->toHaveCount(1);
    expect(round((float) $packaging[0]['cost_price'], 2))->toBe(10.00);

    expect($data)->toHaveKey('bulk_cost_per_unit');
    expect(round((float) $data['bulk_cost_per_unit'], 2))->toBe(10.00);

    expect($data)->toHaveKey('cif_percentage');
    expect((float) $data['cif_percentage'])->toBe(50.0);

    expect($data)->toHaveKey('remnant_bulk_cost');
    expect(round((float) $data['remnant_bulk_cost'], 2))->toBe(75.00);
});

it('preview costs rejects invalid remnant_quantity_gallons', function () {
    $this->post(route('production-orders.start', $this->order));

    $response = $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [
            ['id' => $this->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
        'remnant_quantity_gallons' => -1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['remnant_quantity_gallons']);
});
