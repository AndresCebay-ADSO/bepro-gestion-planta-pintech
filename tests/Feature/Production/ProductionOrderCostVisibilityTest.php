<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Enums\SystemRole;
use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductionRemnant;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\RemnantConsumption;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    $unit = UnitOfMeasure::factory()->create(['code' => 'kg', 'name' => 'Kilo', 'symbol' => 'kg']);
    $rmCat = RawMaterialCategory::factory()->create(['code' => 'RMC', 'name' => 'RM Cat', 'is_active' => true]);
    $pCat = ProductCategory::factory()->create(['name' => 'Prod Cat']);

    $product = Product::factory()->create([
        'code' => 'P-COST',
        'name' => 'Pintura Cost Test',
        'category_id' => $pCat->id,
        'unit_of_measure_id' => $unit->id,
        'cif_percentage' => 25,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $formula = Formula::factory()->create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'notes' => 'Test',
        'created_by' => User::factory()->create()->id,
    ]);

    $warehouse = Warehouse::factory()->create([
        'name' => 'Planta',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $rawMaterial = RawMaterial::factory()->create([
        'code' => 'RM-COST-01',
        'category_id' => $rmCat->id,
        'unit_of_measure_id' => $unit->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'code' => 'P-COST-GAL',
        'name' => 'Pintura Cost Test - Galón',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->productionOrder = ProductionOrder::factory()->create([
        'order_number' => 'OP-COST-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->toDateString(),
        'status' => ProductionOrderStatus::Pending,
        'created_by' => User::factory()->create()->id,
    ]);

    ProductionOrderDetail::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $rawMaterial->id,
        'planned_quantity' => 100,
        'unit_cost' => 10,
        'total_cost' => 1000,
    ]);

    ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $variant->id,
        'planned_units' => 20,
    ]);
});

test('operator show payload does not expose cost fields', function () {
    $operator = userWithRole(SystemRole::Operator, ['email_verified_at' => now()]);

    $this->actingAs($operator)
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Show')
            ->where('can.previewCosts', false)
            ->missing('order.total_bulk_cost')
            ->missing('order.total_finished_cost')
            ->missing('order.details.0.unit_cost')
            ->missing('order.details.0.total_cost')
            ->missing('order.packaging_plans.0.cost_price')
            ->missing('order.packaging_plans.0.package_unit_cost_estimate'));
});

test('production user show payload does not expose cost fields', function () {
    // Matriz: los costos de la orden solo con costs.view (Admin y SuperAdmin).
    $user = userWithRole(SystemRole::Production, ['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Show')
            ->where('can.previewCosts', false)
            ->missing('order.total_bulk_cost')
            ->missing('order.details.0.unit_cost')
            ->missing('order.details.0.total_cost'));
});

test('admin show payload includes cost fields', function () {
    $admin = userWithRole(SystemRole::Admin, ['email_verified_at' => now()]);

    $this->actingAs($admin)
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Show')
            ->where('can.previewCosts', true)
            ->where('order.total_bulk_cost', '1000.0000')
            ->where('order.details.0.unit_cost', '10.0000')
            ->where('order.details.0.total_cost', '1000.0000'));
});

/**
 * Saldo generado por la orden y consumo de ese saldo en la misma orden, ambos con costo.
 */
function attachRemnantWithConsumption(ProductionOrder $order): void
{
    $remnant = ProductionRemnant::forceCreate([
        'source_order_id' => $order->id,
        'product_id' => $order->product_id,
        'warehouse_id' => $order->warehouse_id,
        'original_quantity_gallons' => 10,
        'original_quantity_kg' => 50,
        'available_quantity_gallons' => 8,
        'available_quantity_kg' => 40,
        'density_kg_per_gallon' => 5,
        'cost_per_gallon' => 5.5,
        'status' => RemnantStatus::Available,
        'created_by' => $order->created_by,
    ]);

    RemnantConsumption::forceCreate([
        'remnant_id' => $remnant->id,
        'target_order_id' => $order->id,
        'quantity_gallons' => 2,
        'quantity_kg' => 10,
        'consumed_cost' => 11,
        'consumed_by' => $order->created_by,
        'consumed_at' => now(),
    ]);
}

test('roles without costs.view do not receive remnant costs or CIF in the order detail', function (SystemRole $role) {
    // Matriz, principio 1: el costo de los saldos y el CIF % solo con costs.view.
    attachRemnantWithConsumption($this->productionOrder);

    $this->actingAs(userWithRole($role, ['email_verified_at' => now()]))
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.remnant.available_quantity_gallons', 8)
            ->where('order.remnant_consumptions.0.quantity_gallons', 2)
            ->missing('order.product.cif_percentage')
            ->missing('order.remnant.cost_per_gallon')
            ->missing('order.remnant_consumptions.0.consumed_cost'));
})->with([SystemRole::Production, SystemRole::Operator]);

test('admin receives remnant costs and CIF in the order detail', function () {
    attachRemnantWithConsumption($this->productionOrder);

    $this->actingAs(userWithRole(SystemRole::Admin, ['email_verified_at' => now()]))
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('order.product.cif_percentage', 25)
            ->where('order.remnant.cost_per_gallon', 5.5)
            ->where('order.remnant_consumptions.0.consumed_cost', 11));
});

test('export payload without costs omits remnant costs and CIF', function () {
    attachRemnantWithConsumption($this->productionOrder);

    $payload = app(BuildProductionOrderExportDataAction::class)
        ->execute($this->productionOrder, includeCosts: false);

    expect($payload['product'])->not->toHaveKey('cif_percentage')
        ->and($payload['remnant'])->not->toHaveKey('cost_per_gallon')
        ->and($payload['remnant_consumptions'][0])->not->toHaveKey('consumed_cost');
});

test('operator export payload does not expose cost fields', function () {
    $operator = userWithRole(SystemRole::Operator, ['email_verified_at' => now()]);

    $this->actingAs($operator);

    $payload = app(BuildProductionOrderExportDataAction::class)
        ->execute($this->productionOrder, includeCosts: false);

    expect($payload)->not->toHaveKey('total_bulk_cost');
    expect($payload)->not->toHaveKey('total_finished_cost');
    expect($payload['details'][0])->not->toHaveKey('unit_cost');
    expect($payload['details'][0])->not->toHaveKey('total_cost');
    expect($payload['packaging_plans'][0])->not->toHaveKey('cost_price');
    expect($payload['packaging_plans'][0])->not->toHaveKey('package_unit_cost_estimate');
    expect($payload)->toHaveKey('pdf_materials');
});

test('operator can still export pdf and excel without cost data', function () {
    $operator = userWithRole(SystemRole::Operator, ['email_verified_at' => now()]);

    $this->actingAs($operator)
        ->get(route('production-orders.export-pdf', $this->productionOrder))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($operator)
        ->get(route('production-orders.export-excel', $this->productionOrder))
        ->assertSuccessful();
});

test('orders index sends only the product identity, never its costs', function (SystemRole $role) {
    // El listado enviaba el producto completo: costo, precio interno, CIF, umbral y margen.
    $this->productionOrder->product->forceFill([
        'current_cost' => 80,
        'current_price' => 100,
        'sales_margin' => 30,
    ])->saveQuietly();

    $this->actingAs(userWithRole($role, ['email_verified_at' => now()]))
        ->get(route('production-orders.index'))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Index')
            ->where('orders.data.0.order_number', 'OP-COST-001')
            ->where('orders.data.0.product.name', 'Pintura Cost Test')
            ->where('orders.data.0.formula.version', 1)
            ->where('orders.data.0.warehouse.name', 'Planta')
            ->where('orders.data.0.status', 'pending')
            ->missing('orders.data.0.product.current_cost')
            ->missing('orders.data.0.product.current_price')
            ->missing('orders.data.0.product.cif_percentage')
            ->missing('orders.data.0.product.price_threshold')
            ->missing('orders.data.0.product.sales_margin'));
})->with([SystemRole::Production, SystemRole::Operator, SystemRole::Admin]);
