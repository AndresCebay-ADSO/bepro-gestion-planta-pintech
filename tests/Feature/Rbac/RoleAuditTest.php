<?php

declare(strict_types=1);

use App\Enums\Permission;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Role;

/**
 * Roles personalizados que quedan inválidos cuando cambian las reglas en código (permisos reservados, obligatorios o
 * dependencias). El despliegue solo avisa: nunca cambia permisos por su cuenta.
 */
beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/**
 * Rol guardado directamente en la base, como quedaría tras un cambio de reglas en código.
 *
 * @param  array<int, Permission>  $permissions
 */
function storedCustomRole(string $name, array $permissions): Role
{
    /** @var Role $role */
    $role = Role::create(['name' => $name, 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $permissions));

    return $role;
}

it('termina bien si no hay roles personalizados inválidos', function () {
    storedCustomRole('Calidad', [Permission::DashboardView, Permission::ProductsView]);

    $this->artisan('roles:audit')
        ->expectsOutputToContain('Todos los roles personalizados cumplen las reglas')
        ->assertSuccessful();
});

it('lista los roles personalizados inválidos y termina con error', function () {
    storedCustomRole('Auditor', [Permission::DashboardView, Permission::CostsView, Permission::AuditLogsView]);
    storedCustomRole('Catálogo', [Permission::DashboardView, Permission::ProductsView, Permission::ProductsDeactivate]);
    storedCustomRole('Sin inicio', [Permission::AlertsView]);

    // Rol y problema salen en la misma fila de la tabla: se revisa la salida completa.
    $exitCode = Artisan::call('roles:audit');
    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('Auditor')
        ->and($output)->toContain('Permisos reservados a SuperAdmin: '.Permission::AuditLogsView->value)
        ->and($output)->toContain('Catálogo')
        ->and($output)->toContain(Permission::ProductsDeactivate->value.' necesita: '.Permission::ProductsEdit->value)
        ->and($output)->toContain('Sin inicio')
        ->and($output)->toContain('Faltan permisos obligatorios: '.Permission::DashboardView->value);
});

it('no revisa los roles del sistema', function () {
    // Admin tiene permisos que un rol personalizado no puede tener, pero sus reglas viven en código.
    $this->artisan('roles:audit')->assertSuccessful();
});

it('el seeder avisa de los roles inválidos sin fallar ni cambiar sus permisos', function () {
    $role = storedCustomRole('Auditor', [Permission::DashboardView, Permission::CostsView, Permission::AuditLogsView]);

    $this->artisan('db:seed', ['--class' => RolePermissionSeeder::class, '--force' => true])
        ->expectsOutputToContain('Auditor')
        ->expectsOutputToContain('roles:audit')
        ->assertSuccessful();

    expect($role->fresh()->permissions->pluck('name')->sort()->values()->all())->toBe([
        Permission::AuditLogsView->value,
        Permission::CostsView->value,
        Permission::DashboardView->value,
    ]);
});
