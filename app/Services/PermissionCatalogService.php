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
     * Reglas que incumplen los permisos de un rol personalizado. Lo usan la validación de la pantalla de roles y
     * `roles:audit`, que revisa los roles ya guardados cuando cambian las reglas en código.
     *
     * @param  array<int, string>  $permissionNames
     * @return array{reserved: array<int, string>, missing_required: array<int, string>, missing_dependencies: array<string, array<int, string>>}
     */
    public function customRoleViolations(array $permissionNames): array
    {
        $granted = array_values(array_filter(array_map(
            fn (string $name): ?Permission => Permission::tryFrom($name),
            $permissionNames,
        )));

        $missingDependencies = [];

        foreach ($granted as $permission) {
            $missing = array_filter(
                $permission->dependencies(),
                fn (Permission $dependency): bool => ! in_array($dependency, $granted, true),
            );

            if ($missing !== []) {
                $missingDependencies[$permission->value] = array_values(array_map(
                    fn (Permission $dependency): string => $dependency->value,
                    $missing,
                ));
            }
        }

        return [
            'reserved' => array_values(array_map(
                fn (Permission $permission): string => $permission->value,
                array_filter($granted, fn (Permission $permission): bool => $permission->isReserved()),
            )),
            'missing_required' => array_values(array_diff($this->requiredForCustomRoles(), $permissionNames)),
            'missing_dependencies' => $missingDependencies,
        ];
    }

    /**
     * Descripción legible de las reglas incumplidas; vacía si el rol es válido.
     *
     * @param  array<int, string>  $permissionNames
     * @return array<int, string>
     */
    public function describeCustomRoleViolations(array $permissionNames): array
    {
        $violations = $this->customRoleViolations($permissionNames);
        $lines = [];

        if ($violations['reserved'] !== []) {
            $lines[] = 'Permisos reservados a SuperAdmin: '.implode(', ', $violations['reserved']);
        }

        if ($violations['missing_required'] !== []) {
            $lines[] = 'Faltan permisos obligatorios: '.implode(', ', $violations['missing_required']);
        }

        foreach ($violations['missing_dependencies'] as $permission => $dependencies) {
            $lines[] = "{$permission} necesita: ".implode(', ', $dependencies);
        }

        return $lines;
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
