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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(RolePermissionSeeder::class);
    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'job_title' => 'Analista de Calidad',
        'signature_path' => 'signatures/test.png',
    ]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $category = ProductCategory::create(['name' => 'Test Category']);
    $uom = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galon', 'symbol' => 'gal']);

    $this->product = Product::create([
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
        'order_number' => 'OP-REMNANT-'.substr(uniqid(), -6),
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
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
        'density_kg_per_gallon' => 5,
        'remnant_quantity_kg' => null,
        'remnant_notes' => null,
        'quality_responsible_user_id' => $test->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
        'ingredients' => [
            ['id' => $test->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ];

    $payload = array_merge($defaults, $overrides);

    return $payload;
}

it('requires density when completing', function () {
    $payload = completePayload($this);
    unset($payload['density_kg_per_gallon']);

    $this->post(route('production-orders.complete', $this->order), $payload)
        ->assertSessionHasErrors(['density_kg_per_gallon']);
});

it('saves density on the order when completing', function () {
    // Sin presentaciones, todo queda como saldo: sin envasar ni saldo la OP no se completa (D-OPL1).
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 4.5,
        'remnant_quantity_kg' => 450,
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $this->order->refresh();

    expect((float) $this->order->density_kg_per_gallon)->toBe(4.5);
});

it('does not create a remnant when no surplus gallons reported', function () {
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
        'planned_units' => 100,
    ]);

    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'packaging' => [['id' => $packPlan->id, 'actual_units' => 100]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect(ProductionRemnant::count())->toBe(0);
});

it('creates a remnant when surplus gallons are reported', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_kg' => 40,
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

it('calculates remnant gallons from the weighed kilos and the density', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 3.785,
        'remnant_quantity_kg' => 20.8175,
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    expect((float) $remnant->original_quantity_gallons)->toBe(5.5)
        ->and((float) $remnant->original_quantity_kg)->toBe(20.8175)
        ->and((float) $remnant->density_kg_per_gallon)->toBe(3.785);
});

it('assigns cost per gallon to the remnant when cost is calculated', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_kg' => 25,
    ]))->assertRedirect();

    $remnant = ProductionRemnant::first();

    expect($remnant->cost_per_gallon)->not->toBeNull()
        ->and((float) $remnant->cost_per_gallon)->toBeGreaterThan(0);
});

it('ignora un rendimiento enviado por el cliente: guarda lo envasado más el sobrante', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'remnant_quantity_kg' => 25,
        'actual_yield_quantity' => 3,
    ]))->assertSessionHasNoErrors();

    // 25 kg a 5 kg/gal: 5 gal, y nada envasado.
    expect($this->order->fresh()->actual_quantity)->toBe('5.0000')
        ->and($this->order->fresh()->yield_real_quantity)->toBe('5.0000');
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
        'density_kg_per_gallon' => 5,
        'remnant_quantity_kg' => 25,
        'quality_responsible_user_id' => $this->user->id,
        'line_adjustment_ids' => [],
        'remnant_consumption_ids' => [],
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
        'remnant_quantity_kg' => 25,
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
        'remnant_quantity_kg' => 25,
        'density_kg_per_gallon' => 5,
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

it('preview costs rejects invalid remnant_quantity_kg', function () {
    $this->post(route('production-orders.start', $this->order));

    $response = $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [
            ['id' => $this->detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
        'remnant_quantity_kg' => -1,
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['remnant_quantity_kg']);
});

/** Presentación de 1 galón en la OP, para las pruebas en que algo se envasa además del sobrante. */
function gallonPackagingPlan(object $test): ProductionOrderPackagingPlan
{
    $variant = ProductVariant::create([
        'product_id' => $test->product->id,
        'code' => 'VAR-KILOS-'.uniqid(),
        'name' => 'Galón',
        'unit_of_measure_id' => UnitOfMeasure::first()->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    return ProductionOrderPackagingPlan::create([
        'production_order_id' => $test->order->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);
}

/** Cuerpo para enviar a revisión: los mismos datos de completar, sin quien firma el certificado. */
function submitPayload(object $test, array $overrides = []): array
{
    return array_merge(
        collect(completePayload($test))->except(['quality_responsible_user_id', 'density_kg_per_gallon'])->all(),
        $overrides,
    );
}

it('guarda los kilos pesados tal cual y los galones calculados, en la OP y en el saldo', function () {
    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
    ]))->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    $remnant = ProductionRemnant::query()->where('source_order_id', $order->id)->firstOrFail();

    expect($order->remnant_quantity_kg)->toBe('2.5000')
        ->and($order->remnant_quantity_gallons)->toBe('0.5319')
        ->and($remnant->original_quantity_kg)->toBe('2.5000')
        ->and($remnant->available_quantity_kg)->toBe('2.5000')
        ->and($remnant->original_quantity_gallons)->toBe('0.5319')
        ->and($remnant->available_quantity_gallons)->toBe('0.5319');
});

it('al enviar a revisión y reabrir la orden los kilos siguen tal como se digitaron', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->remnant_quantity_kg)->toBe('2.5000');

    $this->get(route('production-orders.show', $this->order))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('order.remnant_quantity_kg', '2.5000')
        ->where('order.remnant_quantity_gallons', '0.5319'));

    // Devuelta a planta, la orden se reabre con lo pesado.
    $this->post(route('production-orders.reject-review', $this->order), ['reason' => 'Revisar el peso del sobrante'])
        ->assertSessionHasNoErrors();

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress)
        ->and($this->order->fresh()->remnant_quantity_kg)->toBe('2.5000');
});

it('si el peso por galón cambia antes de completar, los galones se recalculan con el nuevo', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->remnant_quantity_gallons)->toBe('0.5319');

    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 5,
        'remnant_quantity_kg' => 2.5,
    ]))->assertSessionHasNoErrors();

    $order = $this->order->fresh();
    $remnant = ProductionRemnant::query()->where('source_order_id', $order->id)->firstOrFail();

    expect($order->remnant_quantity_kg)->toBe('2.5000')
        ->and($order->remnant_quantity_gallons)->toBe('0.5000')
        ->and($remnant->original_quantity_gallons)->toBe('0.5000')
        ->and($remnant->original_quantity_kg)->toBe('2.5000')
        ->and($remnant->density_kg_per_gallon)->toBe('5.0000');
});

it('el rendimiento es lo envasado más los galones del sobrante', function () {
    $plan = gallonPackagingPlan($this);

    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
        'packaging' => [['id' => $plan->id, 'actual_units' => 20]],
    ]))->assertSessionHasNoErrors();

    $order = $this->order->fresh();

    // 20 gal envasados y 2,5 kg ÷ 4,7 kg/gal = 0,5319 gal de sobrante.
    expect($order->status)->toBe(ProductionOrderStatus::Completed)
        ->and($order->actual_quantity)->toBe('20.5319')
        ->and($order->yield_real_quantity)->toBe('20.5319');
});

it('al enviar a revisión guarda el rendimiento calculado, sin pedirlo', function () {
    $plan = gallonPackagingPlan($this);

    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
        'packaging' => [['id' => $plan->id, 'actual_units' => 20]],
        'actual_yield_quantity' => 999,
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->actual_quantity)->toBe('20.5319');
});

it('con sobrante exige el peso por galón al enviar a revisión', function (bool $packed) {
    $packaging = $packed ? [['id' => gallonPackagingPlan($this)->id, 'actual_units' => 20]] : [];

    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'remnant_quantity_kg' => 2.5,
        'packaging' => $packaging,
    ]))
        ->assertSessionHasErrors(['density_kg_per_gallon' => 'Digita el peso por galón para registrar el sobrante.'])
        // Si todo quedó como sobrante, el aviso es el del peso por galón y no «no se envasó nada».
        ->assertSessionDoesntHaveErrors('packaging');

    expect($this->order->fresh()->status)->toBe(ProductionOrderStatus::InProgress);
})->with(['con algo envasado' => true, 'todo como sobrante' => false]);

it('sin sobrante no exige el peso por galón al enviar a revisión', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'packaging' => [['id' => gallonPackagingPlan($this)->id, 'actual_units' => 20]],
    ]))->assertSessionHasNoErrors();
});

it('rechaza un sobrante que no alcanza a dar galones', function (string $route) {
    $this->post(route($route, $this->order), completePayload($this, [
        'density_kg_per_gallon' => 9000,
        'remnant_quantity_kg' => '0.0001',
    ]))->assertSessionHasErrors(['remnant_quantity_kg' => 'El sobrante es demasiado pequeño para registrarlo.']);
})->with(['production-orders.complete', 'production-orders.submit-for-review']);

it('rechaza un sobrante cuyos galones no caben en la columna, sin error del servidor', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'density_kg_per_gallon' => '0.0001',
        'remnant_quantity_kg' => '99999999.9999',
    ]))->assertSessionHasErrors('remnant_quantity_kg');

    expect($this->order->fresh()->remnant_quantity_gallons)->toBeNull();
});

it('ignora los galones que envíe el cliente', function () {
    $this->post(route('production-orders.submit-for-review', $this->order), submitPayload($this, [
        'remnant_quantity_gallons' => 999,
    ]))->assertSessionHasErrors('packaging');

    $this->post(route('production-orders.complete', $this->order), completePayload($this, [
        'density_kg_per_gallon' => 4.7,
        'remnant_quantity_kg' => 2.5,
        'remnant_quantity_gallons' => 999,
    ]))->assertSessionHasNoErrors();

    expect($this->order->fresh()->remnant_quantity_gallons)->toBe('0.5319')
        ->and(ProductionRemnant::query()->where('source_order_id', $this->order->id)->value('original_quantity_gallons'))->toBe('0.5319');
});

it('la vista previa reparte el costo con los kilos y el peso por galón', function () {
    $plan = gallonPackagingPlan($this);

    $data = $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [['id' => $this->detail->id, 'actual_quantity' => 50]],
        'packaging' => [['id' => $plan->id, 'actual_units' => 20]],
        'remnant_quantity_kg' => '2.5',
        'density_kg_per_gallon' => '4.7',
        'remnant_quantity_gallons' => 999,
    ])->assertOk()->json();

    // 50 × 5 = 250, entre 20 envasados + 0,5319 de sobrante.
    expect($data['bulk_cost_per_unit'])->toBe('12.1762')
        ->and($data['remnant_bulk_cost'])->not->toBeNull();
});

it('la vista previa sin peso por galón responde bien y calcula sin sobrante', function () {
    $plan = gallonPackagingPlan($this);

    $data = $this->postJson(route('production-orders.preview-costs', $this->order), [
        'ingredients' => [['id' => $this->detail->id, 'actual_quantity' => 50]],
        'packaging' => [['id' => $plan->id, 'actual_units' => 20]],
        'remnant_quantity_kg' => '2.5',
    ])->assertOk()->json();

    expect($data['bulk_cost_per_unit'])->toBe('12.5000')
        ->and($data['remnant_bulk_cost'])->toBeNull();
});

it('los errores nombran el peso por galón, el sobrante pesado y el derrame en kilos', function () {
    $payload = completePayload($this, [
        'density_kg_per_gallon' => '',
        'remnant_quantity_kg' => '-1',
        'spillage_quantity' => '-1',
    ]);

    $this->post(route('production-orders.complete', $this->order), $payload)->assertSessionHasErrors([
        'density_kg_per_gallon' => 'El campo peso por galón (kg/gal) es obligatorio.',
        'remnant_quantity_kg' => 'El campo sobrante pesado (kg) debe ser al menos 0.',
        'spillage_quantity' => 'El campo derrame (kg) debe ser al menos 0.',
    ]);

    expect(__('validation.attributes.density_kg_per_gallon'))->toBe('peso por galón');
});
