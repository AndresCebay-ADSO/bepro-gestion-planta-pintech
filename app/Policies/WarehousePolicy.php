<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canAny([
            Permission::WarehousesView->value,
            Permission::WarehousesViewAll->value,
        ]);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        if ($user->can(Permission::WarehousesViewAll->value)) {
            return true;
        }

        if (! $user->can(Permission::WarehousesView->value)) {
            return false;
        }

        // Sin view_all, solo las bodegas asignadas al usuario.
        return $user->warehouses()->where('warehouses.id', $warehouse->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::WarehousesCreate->value);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesEdit->value);
    }

    public function assignUsers(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesAssignUsers->value);
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->can(Permission::WarehousesDelete->value);
    }
}
