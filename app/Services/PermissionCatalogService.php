<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Permission;
use App\Enums\PermissionModule;

/**
 * Catálogo de permisos agrupado por módulo para la pantalla de roles (docs/PLAN_FASE_2_RBAC.md, 2.4).
 */
class PermissionCatalogService
{
    /**
     * Permisos que todo rol personalizado debe tener: sin dashboard.view, sus usuarios caen en un 403 al iniciar sesión
     * (la página de inicio tras el login es el dashboard).
     */
    private const REQUIRED_FOR_CUSTOM_ROLES = [Permission::DashboardView];

    /**
     * Módulos con sus permisos. Sin `$includeReserved` se omiten los permisos reservados a SuperAdmin
     * y los módulos que quedan vacíos.
     *
     * @return array<int, array{key: string, label: string, permissions: array<int, array{name: string, label: string, required: bool, dependencies: array<int, string>}>}>
     */
    public function modules(bool $includeReserved = false): array
    {
        $modules = [];

        foreach (PermissionModule::cases() as $module) {
            $permissions = array_values(array_filter(
                $module->permissions(),
                fn (Permission $permission): bool => $includeReserved || ! $permission->isReserved(),
            ));

            if ($permissions === []) {
                continue;
            }

            $modules[] = [
                'key' => $module->value,
                'label' => $module->label(),
                'permissions' => array_map(fn (Permission $permission): array => [
                    'name' => $permission->value,
                    'label' => $permission->label(),
                    'required' => in_array($permission, self::REQUIRED_FOR_CUSTOM_ROLES, true),
                    'dependencies' => array_map(
                        fn (Permission $dependency): string => $dependency->value,
                        $permission->dependencies(),
                    ),
                ], $permissions),
            ];
        }

        return $modules;
    }

    /**
     * Permisos que todo rol creado desde la UI debe incluir.
     *
     * @return array<int, string>
     */
    public function requiredForCustomRoles(): array
    {
        return array_map(fn (Permission $permission): string => $permission->value, self::REQUIRED_FOR_CUSTOM_ROLES);
    }

    /**
     * Permisos que puede tener un rol creado desde la UI.
     *
     * @return array<int, string>
     */
    public function assignableToCustomRoles(): array
    {
        return array_values(array_map(
            fn (Permission $permission): string => $permission->value,
            array_filter(Permission::cases(), fn (Permission $permission): bool => ! $permission->isReserved()),
        ));
    }
}
