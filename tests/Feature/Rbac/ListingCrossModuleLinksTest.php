<?php

declare(strict_types=1);

use App\Enums\PaintDevelopmentRequestStatus;
use App\Enums\Permission;
use App\Enums\QuotationStatus;
use App\Enums\SystemRole;
use App\Models\PaintDevelopmentRequest;
use App\Models\QrCode;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

/*
 * Los listados envían los permisos de sus enlaces y acciones de fila: un identificador que enlaza a otro módulo
 * queda como texto sin el permiso de esa pantalla, y una acción del menú «⋯» se oculta sin el suyo. Así ninguna
 * lleva a un 403.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->admin = userWithRole(SystemRole::Admin);
});

it('no enlaza el producto de Inventario PT para el operador, que no ve productos', function (): void {
    $operator = userWithRole(SystemRole::Operator);

    expect($operator->can(Permission::FinishedInventoryView->value))->toBeTrue()
        ->and($operator->can(Permission::ProductsView->value))->toBeFalse();

    $this->actingAs($operator)
        ->get(route('finished-inventory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Inventory/FinishedInventory/Index')
            ->where('can.viewProducts', false)
        );
});

it('enlaza el producto de Inventario PT para el admin', function (): void {
    $this->actingAs($this->admin)
        ->get(route('finished-inventory.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.viewProducts', true));
});

it('enlaza la orden de origen de los saldos solo con permiso de ver órdenes', function (User $user, bool $expected): void {
    $this->actingAs($user)
        ->get(route('production.remnants.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Production/Remnants/Index')
            ->where('can.viewProductionOrders', $expected)
        );
})->with([
    'sin permiso de órdenes' => [fn () => userWithPermissions([Permission::ProductionRemnantsView]), false],
    'admin' => [fn () => test()->admin, true],
]);

it('enlaza la materia prima de una alerta solo con permiso de ver materias primas', function (User $user, bool $expected): void {
    $this->actingAs($user)
        ->get(route('alerts.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Alerts/Index')
            ->where('can.viewRawMaterials', $expected)
        );
})->with([
    // Las alertas de variación de precio se ven con el permiso de costos, que no incluye el de materias primas.
    'sin permiso de materias primas' => [fn () => userWithPermissions([Permission::AlertsView, Permission::CostsView]), false],
    'admin' => [fn () => test()->admin, true],
]);

it('no ofrece eliminar fórmulas a quien no tiene formulas.delete, como el admin', function (): void {
    expect($this->admin->can(Permission::FormulasDelete->value))->toBeFalse();

    $this->actingAs($this->admin)
        ->get(route('formulas.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Formulas/Index')
            ->where('can.delete', false)
        );
});

it('ofrece eliminar fórmulas con formulas.delete', function (): void {
    $user = userWithPermissions([Permission::FormulasView, Permission::FormulasDelete]);

    $this->actingAs($user)
        ->get(route('formulas.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('can.delete', true));
});

it('ofrece editar un borrador de cotización solo con quotations.edit', function (array $permissions, bool $expected): void {
    $user = userWithPermissions($permissions);
    Quotation::factory()->create([
        'status' => QuotationStatus::Draft->value,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('quotations.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Quotations/Index')
            ->has('quotations.data', 1)
            ->where('quotations.data.0.can.update', $expected)
        );
})->with([
    'sin permiso de editar' => [[Permission::QuotationsViewOwn], false],
    'con permiso de editar' => [[Permission::QuotationsViewOwn, Permission::QuotationsEdit], true],
]);

it('ofrece editar un borrador de solicitud de desarrollo solo con paint_development_requests.edit', function (array $permissions, bool $expected): void {
    $user = userWithPermissions($permissions);
    PaintDevelopmentRequest::factory()->create([
        'status' => PaintDevelopmentRequestStatus::Draft,
        'created_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->get(route('paint-development-requests.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('PaintDevelopmentRequests/Index')
            ->has('requests.data', 1)
            ->where('requests.data.0.can.update', $expected)
        );
})->with([
    'sin permiso de editar' => [[Permission::PaintDevelopmentRequestsViewOwn], false],
    'con permiso de editar' => [
        [Permission::PaintDevelopmentRequestsViewOwn, Permission::PaintDevelopmentRequestsEdit],
        true,
    ],
]);

it('enlaza la OP del detalle de un QR solo con permiso de ver órdenes', function (SystemRole $role, bool $expected): void {
    $qrCode = QrCode::factory()->create();

    $this->actingAs(userWithRole($role))
        ->get(route('qr-codes.show', $qrCode))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('QrCodes/Show')
            ->where('can.viewProductionOrders', $expected)
        );
})->with([
    // Comercial ve los QR pero no las órdenes de producción.
    'comercial' => [SystemRole::Commercial, false],
    'admin' => [SystemRole::Admin, true],
]);
