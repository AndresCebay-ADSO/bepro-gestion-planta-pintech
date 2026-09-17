<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SystemRole;
use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RolesView->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RolesCreate->value);
    }

    /**
     * Los roles del sistema se gestionan solo en código: el seeder les reasigna sus permisos en cada despliegue.
     */
    public function update(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesEdit->value) && ! SystemRole::isSystem($role->name);
    }

    /**
     * Un rol con usuarios asignados tampoco se elimina, pero eso lo comprueba DeleteRoleAction con bloqueo.
     */
    public function delete(User $user, Role $role): bool
    {
        return $user->can(Permission::RolesDelete->value) && ! SystemRole::isSystem($role->name);
    }
}
