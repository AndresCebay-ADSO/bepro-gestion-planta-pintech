<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
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

    $category = ProductCategory::create(['name' => 'General']);
    $unit = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $product = Product::create([
        'code' => 'P-CANCEL-01',
        'name' => 'Producto Cancelable',
        'category_id' => $category->id,
        'unit_of_measure_id' => $unit->id,
        'current_cost' => 10,
        'cif_percentage' => 20,
        'current_price' => 12,
        'price_threshold' => 5,
    ]);

    $this->formula = Formula::create([
        'product_id' => $product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Planta Cancelaciones',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);
});

function makeOrderForCancellationTest(object $context, ProductionOrderStatus $status, ?string $notes = null): ProductionOrder
{
    return ProductionOrder::create([
        'order_number' => 'OP-CANCEL-'.$status->value.'-'.fake()->unique()->numerify('###'),
        'product_id' => $context->formula->product_id,
        'formula_id' => $context->formula->id,
        'warehouse_id' => $context->warehouse->id,
        'quantity' => 20,
        'status' => $status,
        'planned_date' => now()->addDay(),
        'notes' => $notes,
        'created_by' => $context->user->id,
    ]);
}

test('it cancels a pending production order and stores the reason', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Pending, 'Nota original');

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), [
            'reason' => 'Cliente pausó el pedido',
        ]);

    $response->assertRedirect(route('production-orders.show', $order));
    $response->assertSessionHas('success', 'Orden de producción cancelada con éxito.');

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled);
    expect($order->notes)->toContain('Nota original');
    expect($order->notes)->toContain('Cancelación: Cliente pausó el pedido');
});

test('it cancels an in progress production order without requiring a reason', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::InProgress);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), []);

    $response->assertRedirect(route('production-orders.show', $order));

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled);
    expect($order->notes)->toBeNull();
});

test('it rejects cancelling a completed production order', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Completed);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), [
            'reason' => 'Ya no aplica',
        ]);

    $response->assertRedirect(route('production-orders.show', $order));
    $response->assertSessionHas('error', "No se puede cancelar una orden en estado 'Completada'.");

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Completed);
});
