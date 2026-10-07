<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
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
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(RolePermissionSeeder::class);

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
        'cif_percentage' => 25,
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
        'code' => 'P-01-GAL',
        'name' => 'Pintura - Galón',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 1,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);

    $this->variantCunete = ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'P-01-CUN',
        'name' => 'Pintura - Cuñete',
        'unit_of_measure_id' => $unit->id,
        'presentation_value' => 5,
        'presentation_label' => 'Cuñete',
        'is_active' => true,
    ]);

    $this->productionOrder = ProductionOrder::create([
        'order_number' => 'OP-001',
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
        'product_id' => $product->id,
        'formula_id' => $formula->id,
        'warehouse_id' => $warehouse->id,
        'quantity' => 20,
        'planned_date' => now()->toDateString(),
        'status' => ProductionOrderStatus::Pending,
        'created_by' => $this->user->id,
    ]);
});

test('cannot add a packaging plan to a pending order', function () {
    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_packaging_plan');
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

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});

test('cannot add a packaging plan to a cancelled order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::Cancelled]);

    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 5,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});

test('operator cannot add a packaging plan to a pending review order', function () {
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole(SystemRole::Operator->value);
    $this->actingAs($operator);

    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertForbidden();
    $this->assertDatabaseEmpty('production_order_packaging_plan');
});

test('admin can add a packaging plan to a pending review order', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $data = [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ];

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), $data);

    $response->assertRedirect();
    $this->assertDatabaseHas('production_order_packaging_plan', [
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
    ]);
});

test('cannot delete a packaging plan from a pending order', function () {
    $plan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ]);

    $response = $this->delete(route('production-orders.packaging-plans.destroy', [
        'production_order' => $this->productionOrder->id,
        'plan' => $plan->id,
    ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_packaging_plan', ['id' => $plan->id]);
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

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_packaging_plan', ['id' => $plan->id]);
});

test('operator cannot delete a packaging plan from a pending review order', function () {
    $plan = ProductionOrderPackagingPlan::create([
        'production_order_id' => $this->productionOrder->id,
        'product_variant_id' => $this->variant->id,
        'planned_units' => 10,
    ]);

    $this->productionOrder->update(['status' => ProductionOrderStatus::PendingReview]);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole(SystemRole::Operator->value);
    $this->actingAs($operator);

    $response = $this->delete(route('production-orders.packaging-plans.destroy', [
        'production_order' => $this->productionOrder->id,
        'plan' => $plan->id,
    ]));

    $response->assertForbidden();
    $this->assertDatabaseHas('production_order_packaging_plan', ['id' => $plan->id]);
});

// B55: una presentación va una sola vez por orden. Cada una deja un lote de PT, y el lote se identifica por el número de
// lote de la OP más la presentación: dos filas de la misma presentación dejarían dos lotes indistinguibles.
test('cannot add a presentation that is already in the order packaging plan', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::InProgress]);
    ProductionOrderPackagingPlan::createForVariant($this->productionOrder->id, $this->variant->id, 10);

    $response = $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 4,
    ]);

    $response->assertSessionHasErrors([
        'product_variant_id' => 'Esta presentación ya está en el plan de envasado de la orden.',
    ]);
    expect(ProductionOrderPackagingPlan::query()->where('production_order_id', $this->productionOrder->id)->count())->toBe(1);
});

test('the same presentation can be planned in different orders', function () {
    $otherOrder = $this->productionOrder->replicate(['order_number', 'lot_number']);
    $otherOrder->fill([
        'order_number' => 'OP-002',
        'lot_number' => $this->productionOrder->lot_number + 1,
        'status' => ProductionOrderStatus::InProgress,
    ])->save();
    ProductionOrderPackagingPlan::createForVariant($this->productionOrder->id, $this->variant->id, 10);

    $this->post(route('production-orders.packaging-plans.store', $otherOrder), [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 4,
    ])->assertSessionHasNoErrors();

    $this->assertDatabaseHas('production_order_packaging_plan', [
        'production_order_id' => $otherOrder->id,
        'product_variant_id' => $this->variant->id,
    ]);
});

test('the database rejects a repeated presentation in the same order', function () {
    ProductionOrderPackagingPlan::createForVariant($this->productionOrder->id, $this->variant->id, 10);

    expect(fn () => ProductionOrderPackagingPlan::createForVariant($this->productionOrder->id, $this->variant->id, 4))
        ->toThrow(QueryException::class);
});

test('the order detail only offers presentations that are not in the plan yet', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::InProgress]);
    ProductionOrderPackagingPlan::createForVariant($this->productionOrder->id, $this->variant->id, 10);

    $this->get(route('production-orders.show', $this->productionOrder))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('availableVariants', 1)
            ->where('availableVariants.0.id', $this->variantCunete->id));
});

test('a concurrent request with the same presentation gets the validation message, not a server error', function () {
    $this->productionOrder->update(['status' => ProductionOrderStatus::InProgress]);

    // Simula la otra petición: guarda la misma presentación después de que esta pasó la validación y antes de que la guarde.
    ProductionOrderPackagingPlan::creating(function (): void {
        DB::table('production_order_packaging_plan')->insert([
            'production_order_id' => $this->productionOrder->id,
            'product_variant_id' => $this->variant->id,
            'planned_units' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->post(route('production-orders.packaging-plans.store', $this->productionOrder), [
        'product_variant_id' => $this->variant->id,
        'planned_units' => 4,
    ])->assertSessionHasErrors([
        'product_variant_id' => ProductionOrderPackagingPlan::DUPLICATE_PRESENTATION_MESSAGE,
    ]);
});
