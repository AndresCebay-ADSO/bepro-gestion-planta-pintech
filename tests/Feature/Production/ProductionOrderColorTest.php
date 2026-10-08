<?php

declare(strict_types=1);

use App\Actions\Production\UpdateProductionOrderColorAction;
use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\ProductionOrder;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

// 3.4: el color que pidió el cliente se corrige con una acción aparte, con el permiso de crear órdenes, mientras la
// orden está abierta. Al completarla se congela: el certificado ya se guardó con el nombre de ese momento.

function colorOrder(ProductionOrderStatus $status, ?string $color = 'RAL 3020'): ProductionOrder
{
    return ProductionOrder::factory()->create(['status' => $status, 'color' => $color]);
}

it('corrects the color of an open order and audits the change', function (ProductionOrderStatus $status) {
    actingAsRole(SystemRole::Production);
    $order = colorOrder($status);

    $this->patch(route('production-orders.update-color', $order), ['color' => 'RAL 3000'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('production-orders.show', $order));

    $change = Activity::query()
        ->where('subject_type', ProductionOrder::class)
        ->where('subject_id', $order->id)
        ->where('event', 'updated')
        ->sole();

    expect($order->fresh()->color)->toBe('RAL 3000')
        ->and($change->properties['old']['color'])->toBe('RAL 3020')
        ->and($change->properties['attributes']['color'])->toBe('RAL 3000');
})->with([
    'pendiente' => ProductionOrderStatus::Pending,
    'en curso' => ProductionOrderStatus::InProgress,
    'en revisión' => ProductionOrderStatus::PendingReview,
]);

it('clears the color when it is left empty', function () {
    actingAsRole(SystemRole::Admin);
    $order = colorOrder(ProductionOrderStatus::InProgress);

    $this->patch(route('production-orders.update-color', $order), ['color' => ''])
        ->assertSessionHasNoErrors();

    expect($order->fresh()->color)->toBeNull();
});

it('freezes the color once the order is completed or cancelled', function (ProductionOrderStatus $status) {
    actingAsRole(SystemRole::Admin);
    $order = colorOrder($status);

    $this->patch(route('production-orders.update-color', $order), ['color' => 'RAL 3000'])
        ->assertForbidden();

    expect($order->fresh()->color)->toBe('RAL 3020');
})->with([
    'completada' => ProductionOrderStatus::Completed,
    'cancelada' => ProductionOrderStatus::Cancelled,
]);

it('forbids correcting the color without permission to create orders', function () {
    actingAsRole(SystemRole::Operator);
    $order = colorOrder(ProductionOrderStatus::InProgress);

    $this->patch(route('production-orders.update-color', $order), ['color' => 'RAL 3000'])
        ->assertForbidden();

    expect($order->fresh()->color)->toBe('RAL 3020');
});

it('rejects a color longer than 100 characters', function () {
    actingAsRole(SystemRole::Admin);
    $order = colorOrder(ProductionOrderStatus::Pending);

    $this->patch(route('production-orders.update-color', $order), ['color' => str_repeat('R', 101)])
        ->assertSessionHasErrors('color');

    expect($order->fresh()->color)->toBe('RAL 3020');
});

it('refuses the change if the order was completed after the permission check', function () {
    // La política vio la orden abierta, pero otra petición la completó antes: la acción vuelve a mirar con la fila
    // bloqueada.
    $order = colorOrder(ProductionOrderStatus::InProgress);
    $order->newQuery()->whereKey($order->id)->update(['status' => ProductionOrderStatus::Completed->value]);

    expect(fn () => app(UpdateProductionOrderColorAction::class)->execute($order, 'RAL 3000'))
        ->toThrow(DomainException::class);

    expect($order->fresh()->color)->toBe('RAL 3020');
});

it('offers the correction on the order detail only while it can be done', function () {
    actingAsRole(SystemRole::Production);
    $open = colorOrder(ProductionOrderStatus::Pending);
    $completed = colorOrder(ProductionOrderStatus::Completed);

    $this->get(route('production-orders.show', $open))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('can.updateColor', true)
            ->where('order.color', 'RAL 3020')
            ->where('order.product_display_name', "{$open->product->name} RAL 3020"));

    $this->get(route('production-orders.show', $completed))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.updateColor', false));
});
