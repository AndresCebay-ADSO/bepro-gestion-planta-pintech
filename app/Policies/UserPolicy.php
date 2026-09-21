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
     * Ver la imagen original de la firma (la que muestran el perfil, la edición de usuario y la ficha de la orden):
     * - el propio usuario;
     * - quien puede editarlo: misma regla que update(), así Admin no ve la de un SuperAdmin por esta vía;
     * - quien ve órdenes de producción, solo si el dueño es firmante de certificados (production_orders.complete):
     *   la ficha de la orden muestra quién firma o firmó el certificado.
     *
     * El certificado PDF no depende de esta regla: lleva la firma incrustada y se descarga desde Códigos QR o desde la
     * landing pública del QR.
     */
    public function viewSignature(User $user, User $target): bool
    {
        if ($user->id === $target->id) {
            return true;
        }

        if ($user->can(Permission::UsersEdit->value) && $this->canManageTarget($user, $target)) {
            return true;
        }

        return $user->can(Permission::ProductionOrdersView->value)
            && $target->can(Permission::ProductionOrdersComplete->value);
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
