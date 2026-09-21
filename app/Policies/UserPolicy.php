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

    /**
     * Ver la imagen de la firma:
     * - el propio usuario;
     * - quien puede editarlo (formulario de usuario): misma regla que update(), así Admin no ve la de un SuperAdmin;
     * - quien completa órdenes: elige al firmante del certificado de calidad, que puede ser cualquier usuario con
     *   production_orders.complete, SuperAdmin incluido.
     */
    public function viewSignature(User $user, User $target): bool
    {
        return $user->id === $target->id
            || ($user->can(Permission::UsersEdit->value) && $this->canManageTarget($user, $target))
            || $user->can(Permission::ProductionOrdersComplete->value);
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
