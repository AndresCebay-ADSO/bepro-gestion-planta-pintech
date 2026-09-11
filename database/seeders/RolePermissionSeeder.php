<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Permission;
use App\Enums\SystemRole;
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
 * - Nunca toca los roles creados desde la UI.
 */
class RolePermissionSeeder extends Seeder
{
    private const GUARD = 'web';

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
