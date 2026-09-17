<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class UpdateRoleAction
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function execute(Role $role, string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($role, $name, $permissions): Role {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);

            if (SystemRole::isSystem($lockedRole->name)) {
                throw new \DomainException('Los roles del sistema solo se modifican en código.');
            }

            $previousName = $lockedRole->name;
            $previousPermissions = $lockedRole->permissions->pluck('name')->all();
            $added = collect($permissions)->diff($previousPermissions)->sort()->values()->all();
            $removed = collect($previousPermissions)->diff($permissions)->sort()->values()->all();

            $lockedRole->update(['name' => $name]);
            $lockedRole->syncPermissions($permissions);

            if ($previousName !== $name || $added !== [] || $removed !== []) {
                activity('security')
                    ->performedOn($lockedRole)
                    ->event('role_updated')
                    ->withProperties([
                        'old_name' => $previousName,
                        'name' => $name,
                        'added_permissions' => $added,
                        'removed_permissions' => $removed,
                    ])
                    ->log("Rol {$name} modificado: ".count($added).' permisos añadidos y '.count($removed).' retirados');
            }

            return $lockedRole;
        });
    }
}
