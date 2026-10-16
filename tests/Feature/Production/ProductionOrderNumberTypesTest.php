<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionCost;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
 * Auditoría 3.6, PR 4 (esquema): el dinero de la OP cabe en 16,4 (OPC-01, OPL-03), el responsable en 150 caracteres
 * (OPL-04), la calidad con un decimal y sus topes (PRD-01 en la OP) y las unidades son enteras (OPC-03, D-OP1).
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Queue::fake();
    Storage::fake('local');
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['job_title' => 'Jefe de producción', 'signature_path' => 'signatures/a.png']);
    $this->admin->assignRole(SystemRole::Admin->value);

    $this->kg = UnitOfMeasure::create(['code' => 'KGN', 'name' => 'Kilogramo tipos', 'symbol' => 'kgn', 'to_kg_conversion' => 1]);
    $category = ProductCategory::create(['name' => 'Cat tipos de número']);
    $this->product = Product::create(['name' => 'Esmalte tipos de número', 'category_id' => $category->id,
        'unit_of_measure_id' => $this->kg->id, 'current_cost' => 10, 'cif_percentage' => 10, 'current_price' => 10,
        'price_threshold' => 0]);
    $this->formula = Formula::create(['product_id' => $this->product->id, 'version' => 1, 'is_active' => true,
        'created_by' => $this->admin->id]);
    $this->warehouse = Warehouse::factory()->factory()->create();
    $this->variantA = ProductVariant::factory()->create(['product_id' => $this->product->id, 'presentation_value' => 1,
        'package_raw_material_id' => null, 'label_raw_material_id' => null]);
    $this->variantB = ProductVariant::factory()->create(['product_id' => $this->product->id, 'presentation_value' => 5,
        'package_raw_material_id' => null, 'label_raw_material_id' => null]);
});

/**
 * OP con dos ingredientes y dos presentaciones (A de 1 gal y B de 5 gal).
 *
 * @return array{order: ProductionOrder, d1: ProductionOrderDetail, d2: ProductionOrderDetail, pA: ProductionOrderPackagingPlan, pB: ProductionOrderPackagingPlan}
 */
function numberTypesOrder(object $t, ProductionOrderStatus $status, string $unitPrice = '100', string $stock = '1000'): array
{
    $material = function () use ($t, $unitPrice, $stock): RawMaterial {
        $rawMaterial = RawMaterial::factory()->create(['unit_of_measure_id' => $t->kg->id, 'current_price' => $unitPrice]);
        InventoryBatch::factory()->create(['raw_material_id' => $rawMaterial->id, 'warehouse_id' => $t->warehouse->id,
            'initial_quantity' => $stock, 'remaining_quantity' => $stock, 'unit_price' => $unitPrice,
            'entry_date' => '2026-01-01']);

        return $rawMaterial;
    };

    $order = ProductionOrder::factory()->create([
        'product_id' => $t->product->id, 'formula_id' => $t->formula->id, 'warehouse_id' => $t->warehouse->id,
        'quantity' => 15, 'status' => $status, 'created_by' => $t->admin->id, 'spillage_quantity' => 0,
    ]);
    $d1 = ProductionOrderDetail::create(['production_order_id' => $order->id, 'raw_material_id' => $material()->id,
        'planned_quantity' => 10, 'unit_cost' => $unitPrice, 'total_cost' => 0]);
    $d2 = ProductionOrderDetail::create(['production_order_id' => $order->id, 'raw_material_id' => $material()->id,
        'planned_quantity' => 5, 'unit_cost' => $unitPrice, 'total_cost' => 0]);
    $pA = ProductionOrderPackagingPlan::createForVariant((int) $order->id, (int) $t->variantA->id, '5');
    $pB = ProductionOrderPackagingPlan::createForVariant((int) $order->id, (int) $t->variantB->id, '2');

    return compact('order', 'd1', 'd2', 'pA', 'pB');
}

/**
 * Cuerpo válido para completar o enviar a revisión: 5 u de A (5 gal) + 2 u de B (10 gal) = 15 gal.
 *
 * @param  array{d1: ProductionOrderDetail, d2: ProductionOrderDetail, pA: ProductionOrderPackagingPlan, pB: ProductionOrderPackagingPlan}  $f
 * @return array<string, mixed>
 */
function numberTypesCompleteBody(object $t, array $f, array $override = []): array
{
    return array_replace([
        'ingredients' => [['id' => $f['d1']->id, 'actual_quantity' => '10'], ['id' => $f['d2']->id, 'actual_quantity' => '5']],
        'packaging' => [['id' => $f['pA']->id, 'actual_units' => '5'], ['id' => $f['pB']->id, 'actual_units' => '2']],
        'actual_yield_quantity' => '15',
        'density_kg_per_gallon' => '5',
        'quality_responsible_user_id' => $t->admin->id,
        'responsible_name' => 'Operario',
    ], $override);
}

test('crea una OP con una línea de ingrediente de más de $100 M (OPC-01)', function (): void {
    // Resina a $30.000/kg, 1,5 kg por unidad y 2.667 unidades: la línea cuesta $120.015.000.
    $resin = RawMaterial::factory()->create(['unit_of_measure_id' => $this->kg->id, 'current_price' => '30000']);
    InventoryBatch::factory()->create(['raw_material_id' => $resin->id, 'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 99999, 'remaining_quantity' => 99999, 'unit_price' => '30000']);
    FormulaDetail::create(['formula_id' => $this->formula->id, 'raw_material_id' => $resin->id, 'quantity' => '1.5',
        'unit_of_measure_id' => $this->kg->id]);

    $this->actingAs($this->admin)->post(route('production-orders.store'), [
        'product_id' => $this->product->id, 'formula_id' => $this->formula->id, 'warehouse_id' => $this->warehouse->id,
        'quantity' => '2667', 'planned_date' => plantToday(), 'packaging' => [],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $detail = ProductionOrderDetail::query()->sole();
    expect(bccomp((string) $detail->total_cost, '120015000', 4))->toBe(0);
});

test('completa una OP cuyo costo pasa de $100 M (OPL-03)', function (string $first, string $second): void {
    $f = numberTypesOrder($this, ProductionOrderStatus::InProgress, '100000', '5000');

    $this->actingAs($this->admin)->post(route('production-orders.complete', $f['order']), numberTypesCompleteBody($this, $f, [
        'ingredients' => [['id' => $f['d1']->id, 'actual_quantity' => $first], ['id' => $f['d2']->id, 'actual_quantity' => $second]],
    ]))->assertSessionHasNoErrors()->assertRedirect();

    expect($f['order']->fresh()->status)->toBe(ProductionOrderStatus::Completed);
    $cost = ProductionCost::query()->where('production_order_id', $f['order']->id)->value('cost');
    expect(bccomp((string) $cost, '100000000', 4))->toBe(1);
})->with([
    'una línea de $120 M' => ['1200', '5'],
    'dos líneas de $60 M' => ['600', '600'],
]);

test('el responsable admite hasta 150 caracteres (OPL-04)', function (string $routeName): void {
    $f = numberTypesOrder($this, ProductionOrderStatus::InProgress);

    $this->actingAs($this->admin)
        ->post(route($routeName, $f['order']), numberTypesCompleteBody($this, $f, ['responsible_name' => str_repeat('R', 151)]))
        ->assertSessionHasErrors('responsible_name');

    $this->actingAs($this->admin)
        ->post(route($routeName, $f['order']), numberTypesCompleteBody($this, $f, ['responsible_name' => str_repeat('R', 150)]))
        ->assertSessionHasNoErrors();

    expect(mb_strlen((string) $f['order']->fresh()->responsible_name))->toBe(150);
})->with(['production-orders.complete', 'production-orders.submit-for-review']);

test('la calidad de la OP lleva un decimal y los topes de cada medición', function (string $routeName, string $field, string $value, bool $valid): void {
    $f = numberTypesOrder($this, ProductionOrderStatus::InProgress);

    $response = $this->actingAs($this->admin)
        ->post(route($routeName, $f['order']), numberTypesCompleteBody($this, $f, [$field => $value]));

    if ($valid) {
        $response->assertSessionHasNoErrors();
        expect(bccomp((string) $f['order']->fresh()->{$field}, $value, 1))->toBe(0);
    } else {
        $response->assertSessionHasErrors($field);
    }
})->with(['production-orders.complete', 'production-orders.submit-for-review'])->with([
    'KU con un decimal' => ['viscosity_ku', '120.5', true],
    'KU con dos decimales' => ['viscosity_ku', '99.99', false],
    'KU por encima de 999,9' => ['viscosity_ku', '1000', false],
    'Hegman con medio punto' => ['grinding_hg', '6.5', true],
    'Hegman por encima de 8' => ['grinding_hg', '8.5', false],
    'sólidos con un decimal' => ['quality_solids', '52.5', true],
    'sólidos con dos decimales' => ['quality_solids', '52.55', false],
    'sólidos por encima de 100' => ['quality_solids', '100.1', false],
]);

test('las unidades envasadas, envases y etiquetas son enteras', function (string $field): void {
    $f = numberTypesOrder($this, ProductionOrderStatus::InProgress);
    $packaging = [
        ['id' => $f['pA']->id, 'actual_units' => '5'],
        ['id' => $f['pB']->id, 'actual_units' => '2'] + [$field => '1.5'],
    ];
    if ($field === 'actual_units') {
        $packaging[1]['actual_units'] = '2.5';
    }

    $this->actingAs($this->admin)
        ->post(route('production-orders.complete', $f['order']), numberTypesCompleteBody($this, $f, [
            'packaging' => $packaging,
            'actual_yield_quantity' => $field === 'actual_units' ? '17.5' : '15',
        ]))
        ->assertSessionHasErrors("packaging.1.{$field}");
})->with(['actual_units', 'new_containers_used', 'labels_used']);

test('las unidades planificadas son enteras al crear la OP y al agregar una presentación (OPC-03)', function (): void {
    $this->actingAs($this->admin)->post(route('production-orders.store'), [
        'product_id' => $this->product->id, 'formula_id' => $this->formula->id, 'warehouse_id' => $this->warehouse->id,
        'quantity' => '2.5', 'planned_date' => plantToday(),
        'packaging' => [['product_variant_id' => $this->variantA->id, 'planned_units' => '2.5']],
    ])->assertSessionHasErrors('packaging.0.planned_units');

    $f = numberTypesOrder($this, ProductionOrderStatus::InProgress);
    $variantC = ProductVariant::factory()->create(['product_id' => $this->product->id, 'presentation_value' => '0.25',
        'package_raw_material_id' => null, 'label_raw_material_id' => null]);

    $this->actingAs($this->admin)
        ->post(route('production-orders.packaging-plans.store', $f['order']), [
            'product_variant_id' => $variantC->id, 'planned_units' => '2.5',
        ])
        ->assertSessionHasErrors('planned_units');
});

test('la cantidad a producir sigue aceptando decimales: una muestra de ⅛ de galón', function (): void {
    $variantEighth = ProductVariant::factory()->create(['product_id' => $this->product->id, 'presentation_value' => '0.125',
        'package_raw_material_id' => null, 'label_raw_material_id' => null]);

    $this->actingAs($this->admin)->post(route('production-orders.store'), [
        'product_id' => $this->product->id, 'formula_id' => $this->formula->id, 'warehouse_id' => $this->warehouse->id,
        'quantity' => '0.125', 'planned_date' => plantToday(),
        'packaging' => [['product_variant_id' => $variantEighth->id, 'planned_units' => '1']],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $order = ProductionOrder::query()->sole();
    expect(bccomp((string) $order->quantity, '0.125', 4))->toBe(0)
        ->and($order->packagingPlans()->sole()->planned_units)->toBe(1);
});
