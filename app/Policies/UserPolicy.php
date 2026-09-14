<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\SystemRole;
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
     * Solo un SuperAdmin puede modificar o eliminar a otro SuperAdmin (docs/MATRIZ_RBAC.md §4).
     */
    private function canManageTarget(User $user, User $target): bool
    {
        return ! $target->hasRole(SystemRole::SuperAdmin->value)
            || $user->hasRole(SystemRole::SuperAdmin->value);
    }
}
