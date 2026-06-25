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
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole(Role::create(['name' => 'admin']));
    $this->actingAs($this->user);

    $this->factory = Warehouse::create([
        'name' => 'Planta Test',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $category = ProductCategory::create(['name' => 'General']);
    $uom = UnitOfMeasure::create(['code' => 'L', 'name' => 'Litro', 'symbol' => 'L']);

    $product = Product::create([
        'code' => 'P-ST',
        'name' => 'Producto State Test',
        'category_id' => $category->id,
        'unit_of_measure_id' => $uom->id,
        'current_cost' => 10,
        'profit_margin' => 0,
        'current_price' => 10,
        'price_threshold' => 0,
    ]);

    $this->formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->material = RawMaterial::create([
        'code' => 'RM-ST',
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
        'code' => 'VAR-ST',
        'name' => 'Producto State Test - Galón',
        'unit_of_measure_id' => $uom->id,
        'presentation_label' => 'Galón',
        'is_active' => true,
    ]);
});

/**
 * Helper to create a production order in a specific state.
 */
function createOrderInState(object $context, ProductionOrderStatus $status): array
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
        'order_number' => 'OP-STATE-'.$status->value,
        'product_id' => $context->formula->product_id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->factory->id,
        'quantity' => 100,
        'status' => $status->value,
        'planned_date' => now(),
        'created_by' => $context->user->id,
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

test('it rejects completing a pending order', function () {
    [$order, $detail] = createOrderInState($this, ProductionOrderStatus::Pending);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertForbidden();
    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Pending);
});

test('it allows completing an in_progress order', function () {
    [$order, $detail] = createOrderInState($this, ProductionOrderStatus::InProgress);

    $response = $this->post(route('production-orders.complete', $order), [
        'actual_yield_quantity' => 100,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ]);

    $response->assertRedirect();
    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Completed);
});

test('it rejects completing a cancelled order', function () {
    [$order, $detail] = createOrderInState($this, ProductionOrderStatus::Cancelled);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.complete', $order), [
            'actual_yield_quantity' => 100,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 50],
            ],
            'packaging' => [],
        ]);

    $response->assertForbidden();
    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled);
});

test('it rejects completing an already completed order', function () {
    [$order, $detail] = createOrderInState($this, ProductionOrderStatus::Completed);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.complete', $order), [
            'actual_yield_quantity' => 100,
            'ingredients' => [
                ['id' => $detail->id, 'actual_quantity' => 50],
            ],
            'packaging' => [],
        ]);

    $response->assertForbidden();
});

test('it records final approver after submit reject resubmit complete cycle', function () {
    Role::findOrCreate('operador', 'web');
    Role::findOrCreate('produccion', 'web');

    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole('operador');

    $reviewer = User::factory()->create(['email_verified_at' => now()]);
    $reviewer->assignRole('produccion');

    [$order, $detail] = createOrderInState($this, ProductionOrderStatus::InProgress);

    $operationalPayload = [
        'actual_yield_quantity' => 100,
        'ingredients' => [
            ['id' => $detail->id, 'actual_quantity' => 50],
        ],
        'packaging' => [],
    ];

    $this->actingAs($operator)
        ->post(route('production-orders.submit-for-review', $order), $operationalPayload)
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::PendingReview)
        ->and($order->reviewed_by)->toBeNull()
        ->and($order->reviewed_at)->toBeNull()
        ->and($order->submitted_by)->toBe($operator->id);

    $rejectionReason = 'Cantidad real incorrecta en mezcla';

    $this->actingAs($reviewer)
        ->post(route('production-orders.reject-review', $order), [
            'reason' => $rejectionReason,
        ])
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::InProgress)
        ->and($order->reviewed_by)->toBeNull()
        ->and($order->reviewed_at)->toBeNull()
        ->and($order->rejection_reason)->toBe($rejectionReason);

    $this->actingAs($operator)
        ->post(route('production-orders.submit-for-review', $order), $operationalPayload)
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::PendingReview)
        ->and($order->reviewed_by)->toBeNull()
        ->and($order->reviewed_at)->toBeNull()
        ->and($order->rejection_reason)->toBeNull();

    $this->actingAs($reviewer)
        ->post(route('production-orders.complete', $order), $operationalPayload)
        ->assertRedirect();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Completed)
        ->and($order->reviewed_by)->toBe($reviewer->id)
        ->and($order->reviewed_at)->not->toBeNull();
});
