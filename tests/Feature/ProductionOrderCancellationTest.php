<?php

declare(strict_types=1);

use App\Actions\Production\ConsumeRemnantAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\RemnantStatus;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductionRemnant;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    test()->seed(RolePermissionSeeder::class);

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->assignRole('admin');
    $this->actingAs($this->user);

    $category = ProductCategory::create(['name' => 'General']);
    $unit = UnitOfMeasure::create(['code' => 'GAL', 'name' => 'Galón', 'symbol' => 'gal']);

    $product = Product::create([
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
        'order_number' => 'OP-CAN-'.fake()->unique()->numerify('####'),
        'lot_number' => fake()->unique()->numberBetween(100000, 999999),
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
    $response->assertInertiaFlash('success', 'Orden de producción cancelada con éxito.');

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled);
    expect($order->notes)->toContain('Nota original');
    expect($order->notes)->toContain('Cancelación: Cliente pausó el pedido');
});

test('it cancels an in progress production order with a reason', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::InProgress);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), ['reason' => 'Se canceló la venta que la pedía']);

    $response->assertRedirect(route('production-orders.show', $order));

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled)
        ->and($order->notes)->toBe('Cancelación: Se canceló la venta que la pedía');
});

test('it rejects cancelling a completed production order', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Completed);

    $response = $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), [
            'reason' => 'Ya no aplica',
        ]);

    $response->assertRedirect(route('production-orders.show', $order));
    $response->assertInertiaFlash('error', "No se puede cancelar una orden en estado 'Completada'.");

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Completed);
});

// Auditoría 3.6, PR 10 (D-OPX2, P10-3): botón «Cancelar orden» en el detalle, con motivo obligatorio.

it('requires a reason of at least 10 characters to cancel', function (?string $reason) {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Pending);

    $this->post(route('production-orders.cancel', $order), ['reason' => $reason])
        ->assertSessionHasErrors('reason');

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::Pending);
})->with([null, '', 'corto', '         x', str_repeat('a', 1001)]);

it('tells the page whether the order can be cancelled', function (ProductionOrderStatus $status, bool $expected) {
    $order = makeOrderForCancellationTest($this, $status);

    $this->get(route('production-orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('can.cancel', $expected));
})->with([
    'pendiente' => [ProductionOrderStatus::Pending, true],
    'en curso' => [ProductionOrderStatus::InProgress, true],
    'en revisión' => [ProductionOrderStatus::PendingReview, false],
    'completada' => [ProductionOrderStatus::Completed, false],
    'cancelada' => [ProductionOrderStatus::Cancelled, false],
]);

it('does not offer cancelling to a role without the cancel permission', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Pending);
    $production = User::factory()->create(['email_verified_at' => now()]);
    $production->assignRole(SystemRole::Production->value);

    $this->actingAs($production)->get(route('production-orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('can.cancel', false));

    $this->actingAs($production)->post(route('production-orders.cancel', $order), ['reason' => 'Intento sin permiso'])
        ->assertForbidden();
});

it('answers a lost race with an error the dialog can show, not only a flash', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::Completed);

    $this->from(route('production-orders.show', $order))
        ->post(route('production-orders.cancel', $order), ['reason' => 'La orden ya se había completado'])
        ->assertRedirect(route('production-orders.show', $order))
        ->assertSessionHasErrors(['production_order' => "No se puede cancelar una orden en estado 'Completada'."])
        ->assertInertiaFlash('error');
});

it('keeps consumed remnants consumed and writes them in the cancellation note (provisional, B66)', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::InProgress);
    $source = makeOrderForCancellationTest($this, ProductionOrderStatus::Completed);
    $remnant = ProductionRemnant::create([
        'source_order_id' => $source->id,
        'product_id' => $this->formula->product_id,
        'warehouse_id' => $this->warehouse->id,
        'original_quantity_gallons' => 10,
        'original_quantity_kg' => 50,
        'available_quantity_gallons' => 10,
        'available_quantity_kg' => 50,
        'density_kg_per_gallon' => 5,
        'cost_per_gallon' => 5.5,
        'status' => RemnantStatus::Available,
        'created_by' => $this->user->id,
    ]);
    app(ConsumeRemnantAction::class)->execute($remnant, $order, '2.5', $this->user);

    $this->post(route('production-orders.cancel', $order), ['reason' => 'No es urgente, se hará más adelante'])
        ->assertSessionHasNoErrors();

    $order->refresh();
    expect($order->status)->toBe(ProductionOrderStatus::Cancelled)
        ->and((string) $remnant->fresh()->available_quantity_gallons)->toBe('7.5000')
        ->and($order->notes)->toContain('Cancelación: No es urgente, se hará más adelante')
        ->and($order->notes)->toContain('Saldos consumidos que no vuelven al inventario')
        ->and($order->notes)->toContain($source->order_number)
        ->and($order->notes)->toContain('2.5 gal');
});

it('has the cancellable statuses in one place', function () {
    expect(ProductionOrderStatus::cancellable())->toBe([ProductionOrderStatus::Pending, ProductionOrderStatus::InProgress]);
});

it('lists the consumed remnants in the cancellation note in the order they were consumed', function () {
    $order = makeOrderForCancellationTest($this, ProductionOrderStatus::InProgress);
    $remnant = function (object $context) {
        $source = makeOrderForCancellationTest($context, ProductionOrderStatus::Completed);

        return [$source, ProductionRemnant::create([
            'source_order_id' => $source->id, 'product_id' => $context->formula->product_id,
            'warehouse_id' => $context->warehouse->id, 'original_quantity_gallons' => 10, 'original_quantity_kg' => 50,
            'available_quantity_gallons' => 10, 'available_quantity_kg' => 50, 'density_kg_per_gallon' => 5,
            'cost_per_gallon' => 5.5, 'status' => RemnantStatus::Available, 'created_by' => $context->user->id,
        ])];
    };
    [$firstSource, $first] = $remnant($this);
    [$secondSource, $second] = $remnant($this);
    $consume = app(ConsumeRemnantAction::class);
    $firstConsumption = $consume->execute($first, $order, '1', $this->user);
    $consume->execute($second, $order, '2', $this->user);
    // Un UPDATE mueve la fila: sin ORDER BY, PostgreSQL la devuelve al final en un barrido secuencial.
    $firstConsumption->update(['notes' => 'reubicada']);

    $this->post(route('production-orders.cancel', $order), ['reason' => 'Ya no se va a producir'])->assertSessionHasNoErrors();

    expect($order->fresh()->notes)->toContain("{$firstSource->order_number} 1 gal; {$secondSource->order_number} 2 gal");
});
