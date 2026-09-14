<?php

declare(strict_types=1);

use App\Enums\ProductionOrderStatus;
use App\Enums\SystemRole;
use App\Models\ProductionOrder;
use Illuminate\Support\Facades\Gate;

/**
 * Cada habilidad de la orden combina un permiso de la matriz con la regla de estado (docs/MATRIZ_RBAC.md §4).
 */
function orderInStatus(ProductionOrderStatus $status): ProductionOrder
{
    return ProductionOrder::factory()->create(['status' => $status]);
}

function orderAbility(SystemRole $role, string $ability, ProductionOrder $order): bool
{
    return Gate::forUser(userWithRole($role))->allows($ability, $order);
}

it('permite enviar a revisión a quien tiene el permiso, solo en proceso', function (SystemRole $role, bool $expected) {
    expect(orderAbility($role, 'submitForReview', orderInStatus(ProductionOrderStatus::InProgress)))->toBe($expected)
        ->and(orderAbility($role, 'submitForReview', orderInStatus(ProductionOrderStatus::Pending)))->toBeFalse();
})->with([
    'admin' => [SystemRole::Admin, true],
    'producción' => [SystemRole::Production, true],
    'operador' => [SystemRole::Operator, true],
    'comercial' => [SystemRole::Commercial, false],
]);

it('en revisión solo opera quien puede completar la orden', function (SystemRole $role, bool $expected) {
    expect(orderAbility($role, 'updateOperationalData', orderInStatus(ProductionOrderStatus::PendingReview)))->toBe($expected);
})->with([
    'admin' => [SystemRole::Admin, true],
    'producción' => [SystemRole::Production, true],
    'operador' => [SystemRole::Operator, false],
]);

it('permite operar en proceso a quien tiene operate y a nadie en estados cerrados', function () {
    expect(orderAbility(SystemRole::Operator, 'updateOperationalData', orderInStatus(ProductionOrderStatus::InProgress)))->toBeTrue()
        ->and(orderAbility(SystemRole::Operator, 'updateOperationalData', orderInStatus(ProductionOrderStatus::Pending)))->toBeFalse()
        ->and(orderAbility(SystemRole::Admin, 'updateOperationalData', orderInStatus(ProductionOrderStatus::Completed)))->toBeFalse()
        ->and(orderAbility(SystemRole::Commercial, 'updateOperationalData', orderInStatus(ProductionOrderStatus::InProgress)))->toBeFalse();
});

it('completa desde en proceso o en revisión solo con el permiso', function () {
    expect(orderAbility(SystemRole::Production, 'complete', orderInStatus(ProductionOrderStatus::InProgress)))->toBeTrue()
        ->and(orderAbility(SystemRole::Production, 'complete', orderInStatus(ProductionOrderStatus::PendingReview)))->toBeTrue()
        ->and(orderAbility(SystemRole::Production, 'complete', orderInStatus(ProductionOrderStatus::Completed)))->toBeFalse()
        ->and(orderAbility(SystemRole::Operator, 'complete', orderInStatus(ProductionOrderStatus::InProgress)))->toBeFalse();
});

it('reserva costos y cancelación a Admin', function (string $ability) {
    $order = orderInStatus(ProductionOrderStatus::InProgress);

    expect(orderAbility(SystemRole::Admin, $ability, $order))->toBeTrue()
        ->and(orderAbility(SystemRole::Production, $ability, $order))->toBeFalse()
        ->and(orderAbility(SystemRole::Operator, $ability, $order))->toBeFalse();
})->with(['previewCosts', 'cancel']);
