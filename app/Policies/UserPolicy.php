<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::UsersCreate->value);
    }

    public function update(User $user, User $target): bool
    {
        return $user->can(Permission::UsersEdit->value) && $this->canManageTarget($user, $target);
    }

    public function manageRoles(User $user): bool
    {
        return $user->can(Permission::UsersManageRoles->value);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->can(Permission::UsersDelete->value) && $this->canManageTarget($user, $target);
    }

    /**
     * Nadie modifica ni elimina a un usuario con permisos que él no tiene (docs/MATRIZ_RBAC.md §4). Así un rol con
     * `users.edit` no puede desactivar a quien tiene más acceso que él, y a un SuperAdmin solo lo gestiona otro.
     */
    private function canManageTarget(User $user, User $target): bool
    {
        return $user->holdsAllPermissions($target->getAllPermissions()->pluck('name'));
    }
}
