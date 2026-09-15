<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Roles protegidos del sistema: no se pueden eliminar ni renombrar desde la UI.
 *
 * Los valores coinciden con la columna `name` de la tabla `roles` (Spatie).
 */
enum SystemRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Production = 'produccion';
    case Operator = 'operador';
    case Commercial = 'comercial';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => __('Super administrador'),
            self::Admin => __('Administrador'),
            self::Production => __('Producción'),
            self::Operator => __('Operador'),
            self::Commercial => __('Comercial'),
        };
    }

    /**
     * Permisos que el seeder asigna a este rol.
     *
     * SuperAdmin recibe todos los permisos (sin Gate::before, ver docs/PLAN_FASE_2_RBAC.md C1).
     *
     * @return array<int, Permission>
     */
    public function defaultPermissions(): array
    {
        if ($this === self::SuperAdmin) {
            return Permission::cases();
        }

        return array_values(array_filter(
            Permission::cases(),
            fn (Permission $permission): bool => in_array($this, $permission->defaultRoles(), true),
        ));
    }
}
