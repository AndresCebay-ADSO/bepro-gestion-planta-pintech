<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseType;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\ProductVariant;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (Role::count() === 0) {
        Role::create(['name' => 'admin']);
        Role::create(['name' => 'produccion']);
        Role::create(['name' => 'comercial']);
    }

    $this->user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    $this->user->assignRole('admin');

    $this->actingAs($this->user);

    $unit = UnitOfMeasure::create(['code' => 'gal', 'name' => 'Galón', 'symbol' => 'gal']);
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

    $this->variant = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'P-01-GAL',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->variantCunete = ProductVariant::create([
        'product_id' => $product->id,
        'sku' => 'P-01-CUN',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 5,
        'presentation_label' => 'Cuñete',
        'is_active' => true,
    ]);

    $this->productionOrder = ProductionOrder::create([
        'order_number' => 'OP-001',
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'planned_date' => now()->toDateString(),
        'status' => ProductionOrderStatus::Pending,
        'created_by' => $this->user->id,
    ]);
});

test('can add a packaging plan to a pending order', function () {
    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_packaging_plan', [
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ]);
});

test('can add a packaging plan to an in-progress order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::InProgress]);

    $data = [
        'product_variant_id' => $this->variantCunete->id,
        'planned_units' => 4,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_packaging_plan', [
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variantCunete->id,
        'planned_units' => 4,
    ]);
});

test('cannot add a packaging plan to a completed order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertSessionHasErrors(['production_order']);
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});

test('cannot add a packaging plan to a cancelled order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::Cancelled]);

    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 5,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertSessionHasErrors(['production_order']);
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});

test('can delete a packaging plan from a pending order', function () {
    $plan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ]);

    $response = $this->delete(route('production-orders.packaging-plans.destroy', [
        'production_order' => $this->productionOrder->id,
        'plan' => $plan->id,
    ]));

    $response->assertRedirect();
    $this->assertDatabaseMissing('production_order_packaging_plan', ['id' => $plan->id]);
});

test('cannot delete a packaging plan from a completed order', function () {
    $plan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ]);

    $this->productionOrder->update(['status' => ProductionOrderStatus::Completed]);

    $response = $this->delete(route('production-orders.packaging-plans.destroy', [
        'production_order' => $this->productionOrder->id,
        'plan' => $plan->id,
    ]));

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_packaging_plan', ['id' => $plan->id]);
});

test('validates required fields for packaging plan', function () {
    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), []);

    $response->assertSessionHasErrors(['product_variant_id', 'planned_units']);
});

test('validates product_variant_id must belong to order product', function () {
    // Create a variant for a different product
    $pCat = ProductCategory::first();
    $unit = UnitOfMeasure::first();

    $otherProduct = Product::create([
        'code' => 'P-99',
        'name' => 'Otro Producto',
        'category_id' => $pCat->id,
        'unit_of_measure_id' => $unit->id,
        'profit_margin' => 20,
        'price_threshold' => 2,
        'is_active' => true,
    ]);

    $otherVariant = ProductVariant::create([
        'product_id' => $otherProduct->id,
        'sku' => 'P-99-GAL',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), [
        'product_variant_id' => $otherVariant->id,
        'planned_units' => 10,
    ]);

    $response->assertSessionHasErrors(['product_variant_id']);
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});
