<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\PermissionModule;
use App\Enums\SystemRole;

it('declara los 84 permisos de la matriz con keys únicas en formato modulo.accion', function () {
    $values = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

    expect($values)->toHaveCount(84)
        ->and(array_unique($values))->toHaveCount(84);

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z_]+\.[a-z_]+$/');
    }
});

it('da a cada permiso una etiqueta propia y distinta de su key', function () {
    $labels = array_map(fn (Permission $permission) => $permission->label(), Permission::cases());

    expect(array_unique($labels))->toHaveCount(count($labels));

    foreach (Permission::cases() as $permission) {
        expect($permission->label())->not->toBe('')->not->toBe($permission->value);
    }
});

it('reparte todos los permisos en 21 módulos sin dejar módulos vacíos', function () {
    expect(PermissionModule::cases())->toHaveCount(21);

    $grouped = [];
    foreach (PermissionModule::cases() as $module) {
        expect($module->permissions())->not->toBeEmpty();
        $grouped = [...$grouped, ...$module->permissions()];
    }

    expect($grouped)->toHaveCount(count(Permission::cases()));
});

it('asigna a SuperAdmin todos los permisos', function () {
    expect(SystemRole::SuperAdmin->defaultPermissions())->toBe(Permission::cases());
});

it('asigna a cada rol el número de permisos de la matriz', function (SystemRole $role, int $expected) {
    expect($role->defaultPermissions())->toHaveCount($expected);
})->with([
    'admin' => [SystemRole::Admin, 71],
    'producción' => [SystemRole::Production, 23],
    'operador' => [SystemRole::Operator, 8],
    'comercial' => [SystemRole::Commercial, 22],
]);

it('conserva los nombres de los roles que ya existen en la base de datos', function () {
    expect(SystemRole::Admin->value)->toBe('admin')
        ->and(SystemRole::Production->value)->toBe('produccion')
        ->and(SystemRole::Operator->value)->toBe('operador')
        ->and(SystemRole::Commercial->value)->toBe('comercial');
});

it('usa view_own y view_all en los módulos con dueño, nunca view a secas', function (PermissionModule $module) {
    $actions = array_map(
        fn (Permission $permission) => explode('.', $permission->value)[1],
        $module->permissions(),
    );

    expect($actions)->toContain('view_own', 'view_all')->not->toContain('view');
})->with([
    PermissionModule::Quotations,
    PermissionModule::SalesOrders,
    PermissionModule::PaintDevelopmentRequests,
]);

it('reserva a SuperAdmin la gestión de roles, la auditoría, los catálogos y los borrados físicos', function (Permission $permission) {
    expect($permission->defaultRoles())->toBe([]);
})->with([
    Permission::RolesView,
    Permission::RolesCreate,
    Permission::RolesEdit,
    Permission::RolesDelete,
    Permission::AuditLogsView,
    Permission::CatalogsCreate,
    Permission::CatalogsEdit,
    Permission::CatalogsDelete,
    Permission::UsersDelete,
    Permission::ProductsDelete,
    Permission::FormulasDelete,
    Permission::RawMaterialsDelete,
    Permission::WarehousesDelete,
]);

it('nunca muestra costos fuera de SuperAdmin y Admin', function (Permission $permission) {
    expect($permission->defaultRoles())->toBe([SystemRole::Admin]);
})->with([
    Permission::CostsView,
    Permission::CostsUpdate,
]);
