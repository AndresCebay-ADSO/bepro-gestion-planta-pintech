<?php

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderLineAdjustment;
use App\Models\RawMaterial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;

beforeEach(function () {
    if (Role::count() === 0) {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'produccion']);
        Role::create(['name' => 'comercial']);
        Role::create(['name' => 'operador']);
    }

    $this->user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $this->user->assignRole('admin');

    $this->actingAs($this->user);

    $unit = UnitOfMeasure::create(['code' => 'kg', 'name' => 'Kilo', 'symbol' => 'kg']);
    $rmCat = RawMaterialCategory::create(['code' => 'RMC', 'name' => 'RM Cat', 'is_active' => true]);
    $pCat = ProductCategory::create(['name' => 'Prod Cat']);

    $product = Product::create([
        'code' => 'P-01',
        'name' => 'Pintura',
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
        'notes' => 'Original',
        'created_by' => $this->user->id,
    ]);

    $warehouse = Warehouse::create([
        'name' => 'Planta',
        'city' => 'Cali',
        'type' => WarehouseType::Factory,
        'is_active' => true,
    ]);

    $this->rawMaterial = RawMaterial::create([
        'code' => 'RM-01',
        'category_id' => $rmCat->id,
        'unit_of_measure_id' => $unit->id,
        'current_price' => 10,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);

    $this->productionOrder = ProductionOrder::create([
        'order_number' => 'OP-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 10,
        'planned_date' => now()->toDateString(),
        'status' => ProductionOrderStatus::Pending,
        'created_by' => $this->user->id,
    ]);
});

test('cannot add a line adjustment to a pending order', function () {
    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
        'notes' => 'Some extra notes',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_line_adjustments');
});

test('cannot add a line adjustment to a completed order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_line_adjustments');
});

test('operator cannot add a line adjustment to a pending review order', function () {
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');
    $this->actingAs($operator);

    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_line_adjustments');
});

test('admin can add a line adjustment to a pending review order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $data = [
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Viscosity correction',
    ];

    $response = $this->post(route('production-orders.line-adjustments.store', $this->productionOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_line_adjustments', [
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
    ]);
});

test('cannot delete a line adjustment from a pending order', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Test',
        'created_by' => $this->user->id,
    ]);

    $response = $this->delete(route('production-orders.line-adjustments.destroy', [
        'production_order' => $this->productionOrder->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_line_adjustments', ['id' => $adjustment->id]);
});

test('cannot delete a line adjustment from a completed order', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Test',
        'created_by' => $this->user->id,
    ]);

    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $response = $this->delete(route('production-orders.line-adjustments.destroy', [
        'production_order' => $this->productionOrder->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_line_adjustments', ['id' => $adjustment->id]);
});

test('operator cannot delete a line adjustment from a pending review order', function () {
    $adjustment = ProductionOrderLineAdjustment::create([
        'production_order_id' => $this->productionOrder->id,
        'raw_material_id' => $this->rawMaterial->id,
        'quantity' => 5.5,
        'reason' => 'Test',
        'created_by' => $this->user->id,
    ]);

    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');
    $this->actingAs($operator);

    $response = $this->delete(route('production-orders.line-adjustments.destroy', [
        'production_order' => $this->productionOrder->id,
        'adjustment' => $adjustment->id,
    ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_line_adjustments', ['id' => $adjustment->id]);
});
