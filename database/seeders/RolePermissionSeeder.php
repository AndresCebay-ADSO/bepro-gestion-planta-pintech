<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Services\PermissionCatalogService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Sincroniza los permisos y los roles del sistema con el código (docs/MATRIZ_RBAC.md).
 *
 * Idempotente: se ejecuta en cada despliegue.
 * - Crea los permisos nuevos del enum Permission y elimina los que ya no existen en él.
 * - Reasigna a cada SystemRole exactamente sus permisos por defecto.
 * - Nunca toca los roles creados desde la UI: si alguno incumple las reglas actuales, solo avisa (`roles:audit`).
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = SystemRole::GUARD;

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $this->syncPermissionCatalog();

        foreach (SystemRole::cases() as $systemRole) {
            Role::findOrCreate($systemRole->value, self::GUARD)->syncPermissions(
                array_map(fn (Permission $permission) => $permission->value, $systemRole->defaultPermissions()),
            );
        }

        $registrar->forgetCachedPermissions();

        $this->warnAboutInvalidCustomRoles();
    }

    /**
     * Un cambio de reglas en código puede dejar inválido un rol personalizado. Se avisa en la salida del despliegue.
     */
    private function warnAboutInvalidCustomRoles(): void
    {
        $catalog = app(PermissionCatalogService::class);

        $invalid = Role::query()
            ->where('guard_name', self::GUARD)
            ->with('permissions:id,name')
            ->get()
            ->reject(fn (Role $role): bool => SystemRole::isSystem($role->name))
            ->mapWithKeys(fn (Role $role): array => [
                $role->name => $catalog->describeCustomRoleViolations($role->permissions->pluck('name')->all()),
            ])
            ->filter();

        if ($invalid->isEmpty()) {
            return;
        }

        $this->command?->warn('Roles personalizados que incumplen las reglas de permisos (detalle: php artisan roles:audit):');

        foreach ($invalid as $name => $problems) {
            $this->command?->warn("- {$name}: ".implode(' · ', $problems));
        }
    }

    private function syncPermissionCatalog(): void
    {
        $names = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

        $existing = PermissionModel::query()
            ->where('guard_name', self::GUARD)
            ->pluck('name')
            ->all();

        $now = now();
        $missing = array_map(
            fn (string $name) => ['name' => $name, 'guard_name' => self::GUARD, 'created_at' => $now, 'updated_at' => $now],
            array_values(array_diff($names, $existing)),
        );

        if ($missing !== []) {
            PermissionModel::query()->insert($missing);
        }

        // Permisos que ya no existen en el código: se eliminan junto con sus asignaciones.
        PermissionModel::query()
            ->where('guard_name', self::GUARD)
            ->whereNotIn('name', $names)
            ->delete();
    }
}
