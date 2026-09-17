<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use App\Services\PermissionCatalogService;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Crea un rol personalizado directamente en la base de datos (sin pasar por la validación de la pantalla).
 *
 * @param  array<int, Permission>  $permissions
 */
function createCustomRole(string $name, array $permissions): Role
{
    $role = Role::create(['name' => $name, 'guard_name' => 'web']);
    $role->syncPermissions(array_map(fn (Permission $permission) => $permission->value, $permissions));

    return $role;
}

it('reserva la gestión de roles a SuperAdmin', function (SystemRole $role) {
    actingAsRole($role);

    $this->get(route('roles.index'))->assertForbidden();
    $this->get(route('roles.create'))->assertForbidden();
    $this->post(route('roles.store'), ['name' => 'Calidad', 'permissions' => []])->assertForbidden();
})->with([SystemRole::Admin, SystemRole::Production, SystemRole::Operator, SystemRole::Commercial]);

it('lista los roles con su tipo, número de permisos y usuarios', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $quality = createCustomRole('Jefe de calidad', [Permission::DashboardView]);
    userWithRole(SystemRole::Operator)->syncRoles([$quality->name]);

    $this->get(route('roles.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Index')
            ->where('can.create', true)
            ->where('roles.data', function ($roles) use ($quality) {
                $rows = collect($roles)->keyBy('id');
                $admin = Role::findByName(SystemRole::Admin->value, 'web');

                return $rows[$quality->id]['label'] === 'Jefe de calidad'
                    && $rows[$quality->id]['is_system'] === false
                    && $rows[$quality->id]['permissions_count'] === 1
                    && $rows[$quality->id]['users_count'] === 1
                    && $rows[$quality->id]['can']['update'] === true
                    && $rows[$admin->id]['label'] === 'Administrador'
                    && $rows[$admin->id]['is_system'] === true
                    && $rows[$admin->id]['can']['update'] === false
                    && $rows[$admin->id]['can']['delete'] === false;
            }));
});

it('ofrece solo permisos asignables y los roles existentes como punto de partida', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $assignable = app(PermissionCatalogService::class)->assignableToCustomRoles();

    $this->get(route('roles.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Roles/Create')
            ->where('defaultPermissions', [Permission::DashboardView->value])
            ->where('modules', fn ($modules) => collect($modules)->flatMap(fn ($module) => collect($module['permissions'])->pluck('name'))
                ->sort()->values()->all() === collect($assignable)->sort()->values()->all())
            ->where('templates', function ($templates) use ($assignable) {
                $superAdmin = collect($templates)->firstWhere('label', SystemRole::SuperAdmin->label());

                return collect($superAdmin['permissions'])->sort()->values()->all() === collect($assignable)->sort()->values()->all();
            }));
});

it('crea un rol personalizado con sus permisos y lo registra en la auditoría', function () {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('roles.store'), [
        'name' => '  Jefe   de calidad ',
        'permissions' => [
            Permission::DashboardView->value,
            Permission::ProductsView->value,
            Permission::ProductsDownloadDocuments->value,
        ],
    ])->assertRedirect(route('roles.index'));

    $role = Role::findByName('Jefe de calidad', 'web');

    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe([
        Permission::DashboardView->value,
        Permission::ProductsDownloadDocuments->value,
        Permission::ProductsView->value,
    ])->and(Activity::where('log_name', 'security')->where('event', 'role_created')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('rechaza los permisos reservados a SuperAdmin', function (Permission $permission) {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('roles.store'), ['name' => 'Soporte', 'permissions' => [$permission->value]])
        ->assertSessionHasErrors('permissions.0');

    expect(Role::where('name', 'Soporte')->exists())->toBeFalse();
})->with(array_values(array_filter(Permission::cases(), fn (Permission $permission) => $permission->isReserved())));

it('rechaza un permiso sin sus dependencias', function (array $permissions) {
    actingAsRole(SystemRole::SuperAdmin);

    $this->post(route('roles.store'), [
        'name' => 'Incompleto',
        'permissions' => array_map(fn (Permission $permission) => $permission->value, $permissions),
    ])->assertSessionHasErrors('permissions');

    expect(Role::where('name', 'Incompleto')->exists())->toBeFalse();
})->with([
    'registrar movimientos MP sin ver costos' => [[Permission::InventoryMovementsView, Permission::InventoryMovementsCreate]],
    'desactivar productos sin editarlos' => [[Permission::ProductsView, Permission::ProductsDeactivate]],
    'convertir cotizaciones sin crear pedidos' => [[Permission::QuotationsViewOwn, Permission::QuotationsConvertToOrder]],
]);

it('rechaza nombres de roles del sistema o ya usados', function (string $name) {
    actingAsRole(SystemRole::SuperAdmin);
    createCustomRole('Jefe de calidad', []);

    $this->post(route('roles.store'), ['name' => $name, 'permissions' => []])
        ->assertSessionHasErrors('name');
})->with(['admin', 'Producción', 'SUPER ADMINISTRADOR', 'jefe de calidad']);

it('no permite editar ni eliminar un rol del sistema', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $admin = Role::findByName(SystemRole::Admin->value, 'web');
    $permissionsBefore = $admin->permissions()->count();

    $this->get(route('roles.show', $admin))->assertOk();
    $this->get(route('roles.edit', $admin))->assertForbidden();
    $this->put(route('roles.update', $admin), ['name' => 'Jefa', 'permissions' => []])->assertForbidden();
    $this->delete(route('roles.destroy', $admin))->assertForbidden();

    expect($admin->fresh()->name)->toBe(SystemRole::Admin->value)
        ->and($admin->permissions()->count())->toBe($permissionsBefore);
});

it('actualiza nombre y permisos, aplica el cambio a sus usuarios y registra lo añadido y lo retirado', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $role = createCustomRole('Calidad', [Permission::DashboardView, Permission::AlertsView]);
    $member = userWithRole(SystemRole::Operator);
    $member->syncRoles([$role->name]);

    $this->put(route('roles.update', $role), [
        'name' => 'Control de calidad',
        'permissions' => [Permission::DashboardView->value, Permission::ProductsView->value],
    ])->assertRedirect(route('roles.index'));

    $member = $member->fresh();
    $log = Activity::where('log_name', 'security')->where('event', 'role_updated')->where('subject_id', $role->id)->first();

    expect($role->fresh()->name)->toBe('Control de calidad')
        ->and($member->can(Permission::ProductsView->value))->toBeTrue()
        ->and($member->can(Permission::AlertsView->value))->toBeFalse()
        ->and($log->properties['added_permissions'])->toBe([Permission::ProductsView->value])
        ->and($log->properties['removed_permissions'])->toBe([Permission::AlertsView->value]);
});

it('no elimina un rol con usuarios asignados', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $role = createCustomRole('Calidad', [Permission::DashboardView]);
    $member = userWithRole(SystemRole::Operator);
    $member->syncRoles([$role->name]);

    $this->from(route('roles.index'))
        ->delete(route('roles.destroy', $role))
        ->assertRedirect(route('roles.index'))
        ->assertSessionHas('error');

    expect(Role::whereKey($role->id)->exists())->toBeTrue()
        ->and($member->fresh()->hasRole($role->name))->toBeTrue();
});

it('elimina un rol sin usuarios y lo registra en la auditoría', function () {
    actingAsRole(SystemRole::SuperAdmin);
    $role = createCustomRole('Calidad', [Permission::DashboardView]);

    $this->delete(route('roles.destroy', $role))->assertRedirect(route('roles.index'));

    expect(Role::whereKey($role->id)->exists())->toBeFalse()
        ->and(Activity::where('log_name', 'security')->where('event', 'role_deleted')->where('subject_id', $role->id)->exists())->toBeTrue();
});

it('impide que Admin asigne un rol con permisos que él no tiene', function () {
    actingAsRole(SystemRole::Admin);
    $powerful = createCustomRole('Auditor', [Permission::CostsView, Permission::AuditLogsView]);
    createCustomRole('Calidad', [Permission::DashboardView]);

    $this->get(route('users.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('name')->doesntContain($powerful->name)
                && collect($roles)->pluck('name')->contains('Calidad')));

    $this->post(route('users.store'), [
        'name' => 'Usuario Auditor',
        'email' => 'auditor@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => $powerful->name,
        'is_active' => true,
    ])->assertSessionHasErrors('role');

    expect(User::where('email', 'auditor@test.com')->exists())->toBeFalse();
});

it('permite a Admin asignar un rol personalizado que no supera sus permisos', function () {
    actingAsRole(SystemRole::Admin);
    createCustomRole('Calidad', [Permission::DashboardView, Permission::ProductsView]);

    $this->post(route('users.store'), [
        'name' => 'Usuario Calidad',
        'email' => 'calidad@test.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'role' => 'Calidad',
        'is_active' => true,
    ])->assertRedirect(route('users.index'));

    expect(User::where('email', 'calidad@test.com')->first()->hasRole('Calidad'))->toBeTrue();
});
