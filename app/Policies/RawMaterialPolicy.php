<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\RawMaterial;
use App\Models\User;

class RawMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::RawMaterialsView->value);
    }

    public function view(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->can(Permission::RawMaterialsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::RawMaterialsCreate->value);
    }

    public function update(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->can(Permission::RawMaterialsEdit->value);
    }

    public function deactivate(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->can(Permission::RawMaterialsDeactivate->value);
    }

    public function reactivate(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->can(Permission::RawMaterialsReactivate->value);
    }

    /**
     * Borrado físico: solo si la materia prima nunca se usó (docs/MATRIZ_RBAC.md, principio 3).
     */
    public function delete(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->can(Permission::RawMaterialsDelete->value);
    }
}
