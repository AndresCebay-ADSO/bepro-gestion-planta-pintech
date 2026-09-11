<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\SystemRole;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;

it('crea los permisos del registro y los roles del sistema con sus permisos por defecto', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(Role::count())->toBe(count(SystemRole::cases()));

    foreach (SystemRole::cases() as $systemRole) {
        $expected = array_map(fn (Permission $permission) => $permission->value, $systemRole->defaultPermissions());

        expect(Role::findByName($systemRole->value)->permissions->pluck('name')->sort()->values()->all())
            ->toBe(collect($expected)->sort()->values()->all());
    }
});

it('es idempotente', function () {
    $this->seed(RolePermissionSeeder::class);
    $snapshot = DB::table('role_has_permissions')->count();

    $this->seed(RolePermissionSeeder::class);

    expect(PermissionModel::count())->toBe(count(Permission::cases()))
        ->and(Role::count())->toBe(count(SystemRole::cases()))
        ->and(DB::table('role_has_permissions')->count())->toBe($snapshot);
});

it('elimina los permisos que ya no existen en el código', function () {
    PermissionModel::create(['name' => 'legacy.permission', 'guard_name' => 'web']);

    $this->seed(RolePermissionSeeder::class);

    expect(PermissionModel::where('name', 'legacy.permission')->exists())->toBeFalse();
});

it('restablece los permisos de un rol del sistema modificado a mano', function () {
    $this->seed(RolePermissionSeeder::class);
    Role::findByName(SystemRole::Commercial->value)->givePermissionTo(Permission::CostsView->value);

    $this->seed(RolePermissionSeeder::class);

    expect(Role::findByName(SystemRole::Commercial->value)->hasPermissionTo(Permission::CostsView->value))->toBeFalse();
});

it('no toca los roles creados desde la UI', function () {
    $this->seed(RolePermissionSeeder::class);
    $custom = Role::create(['name' => 'calidad', 'guard_name' => 'web']);
    $custom->givePermissionTo(Permission::QrCodesView->value);

    $this->seed(RolePermissionSeeder::class);

    expect($custom->fresh()->permissions->pluck('name')->all())->toBe([Permission::QrCodesView->value]);
});

it('autentica con actingAsRole un usuario que tiene los permisos de la matriz', function () {
    $user = actingAsRole(SystemRole::Commercial);

    expect(auth()->id())->toBe($user->id)
        ->and($user->can(Permission::QuotationsCreate->value))->toBeTrue()
        ->and($user->can(Permission::CostsView->value))->toBeFalse();
});
