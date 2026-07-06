<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderDetail;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::findOrCreate('operador', 'web');
    Role::findOrCreate('produccion', 'web');
    Role::findOrCreate('admin', 'web');

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole('admin');

    $this->factory = Warehouse::create([
        'name' => 'Planta Test',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'Litro', 'symbol' => 'L']);

    $product = Product::create([
        'code' => 'P-START',
        'name' => 'Producto Start Test',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'cif_percentage' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $this->formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->admin->id,
    ]);

    $this->material = RawMaterial::create([
        'code' => 'RM-START',
        'unit_of_measure_id' => $uom->id,
        'current_price' => 5000,
    ]);

    FormulaDetail::create([
        'formula_id' => $this->formula->id,
        'raw_material_id' => $this->material->id,
        'quantity' => 0.5,
        'unit_of_measure_id' => $uom->id,
    ]);

    ProductVariant::create([
        'product_id' => $product->id,
        'code' => 'VAR-START',
        'name' => 'Producto Start Test - Galón',
        'unit_of_measure_id' => $uom->id,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);
});

function createPendingOrderForStartTest(object $context): array
{
    $batch = InventoryBatch::create([
        'raw_material_id' => $context->material->id,
        'warehouse_id' => $context->factory->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 5,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'order_number' => 'OP-START-001',
        'product_id' => $context->formula->product_id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->factory->id,
        'quantity' => 100,
        'status' => ProductionOrderStatus::Pending,
        'planned_date' => now(),
        'created_by' => $context->admin->id,
    ]);

    $detail = ProductionOrderDetail::create([
        'production_order_id' => $order->id,
        'raw_material_id' => $context->material->id,
        'batch_id' => $batch->id,
        'planned_quantity' => 50,
        'unit_cost' => 5,
        'total_cost' => 250,
    ]);

    return [$order, $detail];
}

test('operator can start a pending production order', function () {
    [$order] = createPendingOrderForStartTest($this);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->post(route('production-orders.start', $order))
        ->assertRedirect(route('production-orders.show', $order))
        ->assertSessionHas('success');

    $order->refresh();

    expect($order->status)->toBe(ProductionOrderStatus::InProgress);
});

test('admin can start a pending production order', function () {
    [$order] = createPendingOrderForStartTest($this);

    $this->actingAs($this->admin)
        ->post(route('production-orders.start', $order))
        ->assertRedirect();

    $order->refresh();

    expect($order->status)->toBe(ProductionOrderStatus::InProgress);
});

test('cannot start an order that is already in progress', function () {
    [$order] = createPendingOrderForStartTest($this);
    $order->update(['status' => ProductionOrderStatus::InProgress]);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->post(route('production-orders.start', $order))
        ->assertForbidden();
});

test('commercial user cannot start production', function () {
    [$order] = createPendingOrderForStartTest($this);

    Role::findOrCreate('comercial', 'web');
    $commercial = User::factory()->create(['email_verified_at' => now()]);
    $commercial->assignRole('comercial');

    $this->actingAs($commercial)
        ->post(route('production-orders.start', $order))
        ->assertForbidden();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Pending);
});

test('operator cannot submit for review while order is still pending', function () {
    [$order, $detail] = createPendingOrderForStartTest($this);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->post(route('production-orders.submit-for-review', $order), [
            'actual_yield_quantity' => 100,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 50],
            ],
            'packaging' => [],
        ])
        ->assertForbidden();
});

test('operator show exposes start production capability for pending orders', function () {
    [$order] = createPendingOrderForStartTest($this);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->get(route('production-orders.show', $order))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Orders/Show')
            ->where('can.startProduction', true)
            ->where('can.submitForReview', false));
});

test('operator show hides start production after order is in progress', function () {
    [$order] = createPendingOrderForStartTest($this);
    $order->update(['status' => ProductionOrderStatus::InProgress]);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $this->actingAs($operator)
        ->get(route('production-orders.show', $order))
        ->assertSuccessful()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.startProduction', false)
            ->where('can.submitForReview', true));
});

test('full operator flow start then submit for review', function () {
    [$order, $detail] = createPendingOrderForStartTest($this);

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $payload = [
        'actual_yield_quantity' => 100,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ];

    $this->actingAs($operator)
        ->post(route('production-orders.start', $order))
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::InProgress);

    $this->actingAs($operator)
        ->post(route('production-orders.submit-for-review', $order), $payload)
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::PendingReview)
        ->and($order->submitted_by)->toBe($operator->id);
});
