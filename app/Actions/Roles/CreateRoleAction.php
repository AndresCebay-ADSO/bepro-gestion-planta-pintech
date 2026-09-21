<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class CreateRoleAction
{
    /**
     * @param  array<int, string>  $permissions
     */
    public function execute(string $name, array $permissions): Role
    {
        return DB::transaction(function () use ($name, $permissions): Role {
            /** @var Role $role */
            $role = Role::create(['name' => $name, 'guard_name' => SystemRole::GUARD]);
            $role->syncPermissions($permissions);

            $sorted = collect($permissions)->sort()->values()->all();

            activity('security')
                ->performedOn($role)
                ->event('role_created')
                ->withProperties(['name' => $name, 'permissions' => $sorted])
                ->log("Rol {$name} creado con ".count($sorted).' permisos');

            return $role;
        });
    }
}
