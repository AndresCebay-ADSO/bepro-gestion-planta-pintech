<?php

declare(strict_types=1);

use App\Enums\Permission;
use App\Enums\PermissionModule;
use App\Enums\SystemRole;

it('declara los 85 permisos de la matriz con keys únicas en formato modulo.accion', function () {
    $values = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

    expect($values)->toHaveCount(85)
        ->and(array_unique($values))->toHaveCount(85);

    foreach ($values as $value) {
        expect($value)->toMatch('/^[a-z_]+\.[a-z_]+$/');
    }
});

it('mantiene el tipo Permission del frontend idéntico al enum', function () {
    $source = file_get_contents(resource_path('js/types/permissions.ts'));
    preg_match_all("/\|\s*'([a-z_]+\.[a-z_]+)'/", (string) $source, $matches);

    $backend = array_map(fn (Permission $permission) => $permission->value, Permission::cases());
    $frontend = $matches[1];

    expect(array_values(array_diff($backend, $frontend)))->toBe([], 'Faltan en resources/js/types/permissions.ts')
        ->and(array_values(array_diff($frontend, $backend)))->toBe([], 'Sobran en resources/js/types/permissions.ts')
        ->and($frontend)->toHaveCount(count(array_unique($frontend)));
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
    'admin' => [SystemRole::Admin, 72],
    'producción' => [SystemRole::Production, 23],
    'operador' => [SystemRole::Operator, 8],
    'comercial' => [SystemRole::Commercial, 22],
]);

it('nombra los roles del sistema en inglés', function () {
    expect(SystemRole::SuperAdmin->value)->toBe('super-admin')
        ->and(SystemRole::Admin->value)->toBe('admin')
        ->and(SystemRole::Production->value)->toBe('production')
        ->and(SystemRole::Operator->value)->toBe('operator')
        ->and(SystemRole::Commercial->value)->toBe('commercial');
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

it('reserva para los roles personalizados exactamente los permisos que solo tiene SuperAdmin', function () {
    $reserved = array_values(array_filter(Permission::cases(), fn (Permission $permission) => $permission->isReserved()));
    $superAdminOnly = array_values(array_filter(Permission::cases(), fn (Permission $permission) => $permission->defaultRoles() === []));

    expect($reserved)->toBe($superAdminOnly);
});

it('da a Admin todos los permisos que no están reservados', function () {
    // UserPolicy deja gestionar a un usuario solo a quien tiene todos sus permisos. Como un rol personalizado no puede
    // tener permisos reservados, esto garantiza que Admin gestiona a cualquier usuario salvo a los SuperAdmin.
    $assignable = array_filter(Permission::cases(), fn (Permission $permission) => ! $permission->isReserved());

    expect(array_values(array_diff(
        array_map(fn (Permission $permission) => $permission->value, $assignable),
        array_map(fn (Permission $permission) => $permission->value, SystemRole::Admin->defaultPermissions()),
    )))->toBe([]);
});

it('cumple las dependencias de cada permiso en los roles del sistema', function (SystemRole $role) {
    $granted = $role->defaultPermissions();
    $missing = [];

    foreach ($granted as $permission) {
        foreach ($permission->dependencies() as $dependency) {
            if (! in_array($dependency, $granted, true)) {
                $missing[] = "{$permission->value} → {$dependency->value}";
            }
        }
    }

    expect($missing)->toBe([]);
})->with(SystemRole::cases());

it('no hace depender un permiso asignable de uno reservado', function () {
    $invalid = [];

    foreach (Permission::cases() as $permission) {
        if ($permission->isReserved()) {
            continue;
        }

        foreach ($permission->dependencies() as $dependency) {
            if ($dependency->isReserved()) {
                $invalid[] = "{$permission->value} → {$dependency->value}";
            }
        }
    }

    expect($invalid)->toBe([]);
});

it('exige ver costos para registrar movimientos de materia prima y para ver la auditoría', function () {
    expect(Permission::InventoryMovementsCreate->dependencies())->toContain(Permission::CostsView)
        ->and(Permission::AuditLogsView->dependencies())->toContain(Permission::CostsView)
        ->and(Permission::ProductsDeactivate->dependencies())->toContain(Permission::ProductsEdit, Permission::ProductsView);
});

it('nunca muestra costos fuera de SuperAdmin y Admin', function (Permission $permission) {
    expect($permission->defaultRoles())->toBe([SystemRole::Admin]);
})->with([
    Permission::CostsView,
    Permission::CostsUpdate,
]);
