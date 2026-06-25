<?php

declare(strict_types=1);

use App\Actions\Production\BuildProductionOrderExportDataAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    foreach (['admin', 'produccion', 'operador'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    $unit = UnitOfMeasure::create(['code' => 'kg', 'name' => 'Kilo', 'symbol' => 'kg']);
    $rmCat = RawMaterialCategory::create(['code' => 'RMC', 'name' => 'RM Cat', 'is_active' => true]);
    $pCat = ProductCategory::create(['name' => 'Prod Cat']);

    $product = Product::create([
        'code' => 'P-COST',
        'name' => 'Pintura Cost Test',
        'category_id' => $pCat->id,
        'unit_of_measure_id' => $unit->id,
        'profit_margin' => 25,
        'price_threshold' => 3,
        'is_active' => true,
    ]);

    $formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'notes' => 'Test',
        'created_by' => User::factory()->create()->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $rawMaterial = RawMaterial::create([
        'code' => 'RM-COST-01',
        'category_id' => $rmCat->id,
        'unit_of_measure_id' => $unit->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $variant = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'P-COST-GAL',
        'name' => 'Pintura Cost Test - Galón',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->productionOrder = ProductionOrder::create([
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
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

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

test('production user show payload includes cost fields', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole('produccion');

    $this->actingAs($user)
        ->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Show')
            ->where('can.previewCosts', true)
            ->where('order.total_bulk_cost', '1000.0000')
            ->where('order.details.0.unit_cost', '10.0000')
            ->where('order.details.0.total_cost', '1000.0000'));
});

test('operator export payload does not expose cost fields', function () {
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

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
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->get(route('production-orders.export-pdf', $this->productionOrder))
        ->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf');

    $this->actingAs($operator)
        ->get(route('production-orders.export-excel', $this->productionOrder))
        ->assertSuccessful();
});
