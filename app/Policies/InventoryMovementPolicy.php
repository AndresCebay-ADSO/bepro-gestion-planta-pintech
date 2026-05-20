<?php

namespace App\Policies;

use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function view(User $user, InventoryMovement $inventoryMovement): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, InventoryMovement $inventoryMovement): bool
    {
        if ($inventoryMovement->production_order_id !== null) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, InventoryMovement $inventoryMovement): bool
    {
        if ($inventoryMovement->production_order_id !== null) {
            return false;
        }

        return $user->hasRole('admin');
    }

    public function restore(User $user, InventoryMovement $inventoryMovement): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return $user->hasRole('admin');
    }
}
