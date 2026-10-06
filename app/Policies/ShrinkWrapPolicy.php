<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShrinkWrap;
use App\Models\User;

/**
 * Registros de termoencogido (docs/MATRIZ_RBAC.md §3, Termoencogido). Inmutables: no hay editar ni eliminar; un error
 * se corrige con un movimiento opuesto y una nota.
 */
class ShrinkWrapPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ShrinkWrapsView->value);
    }

    public function view(User $user, ShrinkWrap $shrinkWrap): bool
    {
        return $user->can(Permission::ShrinkWrapsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShrinkWrapsCreate->value);
    }
}
