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

it('consume saldos solo en una orden en proceso, ni siquiera quien revisa (D-OPX1)', function () {
    expect(orderAbility(SystemRole::Operator, 'consumeRemnant', orderInStatus(ProductionOrderStatus::InProgress)))->toBeTrue()
        ->and(orderAbility(SystemRole::Admin, 'consumeRemnant', orderInStatus(ProductionOrderStatus::PendingReview)))->toBeFalse()
        ->and(orderAbility(SystemRole::Admin, 'consumeRemnant', orderInStatus(ProductionOrderStatus::Completed)))->toBeFalse()
        ->and(orderAbility(SystemRole::Commercial, 'consumeRemnant', orderInStatus(ProductionOrderStatus::InProgress)))->toBeFalse();
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

// Auditoría 3.6, PR 10 (D-OPX2): el botón «Cancelar orden» se muestra con `cancelable` (permiso y estado cancelable). La
// habilidad `cancel` sigue mirando solo el permiso, a propósito: una página vieja que cancela una orden ya completada
// recibe el aviso de la acción, no un 403.
it('ofrece cancelar solo con el permiso y la orden pendiente o en proceso', function (ProductionOrderStatus $status, bool $expected) {
    expect(orderAbility(SystemRole::Admin, 'cancelable', orderInStatus($status)))->toBe($expected)
        ->and(orderAbility(SystemRole::Admin, 'cancel', orderInStatus($status)))->toBeTrue();
})->with([
    'pendiente' => [ProductionOrderStatus::Pending, true],
    'en proceso' => [ProductionOrderStatus::InProgress, true],
    'en revisión' => [ProductionOrderStatus::PendingReview, false],
    'completada' => [ProductionOrderStatus::Completed, false],
    'cancelada' => [ProductionOrderStatus::Cancelled, false],
]);

it('no ofrece cancelar a quien no tiene el permiso', function () {
    expect(orderAbility(SystemRole::Production, 'cancelable', orderInStatus(ProductionOrderStatus::Pending)))->toBeFalse();
});
