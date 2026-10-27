<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Role;

/*
 * Un listado cuyo identificador enlaza a la pantalla de otro módulo envía el permiso de esa pantalla: sin él, el
 * identificador queda como texto en vez de llevar a un 403.
 */

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $this->admin = User::factory()->create(['email_verified_at' => now()]);
    $this->admin->assignRole(SystemRole::Admin->value);
});

/**
 * Usuario con un rol personalizado que solo tiene los permisos dados.
 *
 * @param  list<Permission>  $permissions
 */
function userWithOnly(array $permissions): User
{
    $role = Role::create(['name' => 'Rol de prueba '.uniqid(), 'guard_name' => 'web']);
    $role->givePermissionTo(array_map(fn (Permission $permission): string => $permission->value, $permissions));

    $user = User::factory()->create(['email_verified_at' => now()]);
    $user->assignRole($role);

    return $user;
}

it('no enlaza el producto de Inventario PT para el operador, que no ve productos', function (): void {
    $operator = User::factory()->create(['email_verified_at' => now()]);
    $operator->assignRole(SystemRole::Operator->value);

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
    'sin permiso de órdenes' => [fn () => userWithOnly([Permission::ProductionRemnantsView]), false],
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
    'sin permiso de materias primas' => [fn () => userWithOnly([Permission::AlertsView, Permission::CostsView]), false],
    'admin' => [fn () => test()->admin, true],
]);
