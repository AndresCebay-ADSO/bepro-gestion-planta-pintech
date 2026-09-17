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
     * Nombres que tomarán los roles del sistema al pasarlos a inglés (docs/PLAN_FASE_2_RBAC.md, paso 11).
     */
    private const FUTURE_NAMES = ['production', 'operator', 'commercial'];

    /**
     * Nombres que un rol personalizado no puede usar (en minúsculas): nombre y etiqueta de cada rol del sistema y los
     * nombres del paso 11. Si un rol personalizado se llamara `production`, el renombrado fallaría o el seeder lo
     * tomaría por el rol del sistema y le reasignaría sus permisos.
     *
     * @return array<int, string>
     */
    public static function reservedNames(): array
    {
        $names = self::FUTURE_NAMES;

        foreach (self::cases() as $role) {
            $names[] = mb_strtolower($role->value);
            $names[] = mb_strtolower($role->label());
        }

        return array_values(array_unique($names));
    }

    /**
     * Indica si un nombre de rol pertenece a un rol del sistema (gestionado solo en código).
     */
    public static function isSystem(string $name): bool
    {
        return self::tryFrom($name) !== null;
    }

    /**
     * Etiqueta visible de un rol por su nombre. Los roles creados desde la UI no tienen etiqueta y se muestran por nombre.
     */
    public static function labelFor(string $name): string
    {
        return self::tryFrom($name)?->label() ?? $name;
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
