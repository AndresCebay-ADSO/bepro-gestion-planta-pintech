<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\InventoryMovement;
use App\Models\User;

class InventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::InventoryMovementsView->value);
    }

    public function view(User $user, InventoryMovement $inventoryMovement): bool
    {
        return $user->can(Permission::InventoryMovementsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::InventoryMovementsCreate->value);
    }

    // Los movimientos de materia prima son inmutables (docs/MATRIZ_RBAC.md, principio 5).
    // Un error se corrige con un movimiento compensatorio; el flujo de reverso llega en la Fase 2B.

    public function update(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    public function delete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    public function restore(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }

    public function forceDelete(User $user, InventoryMovement $inventoryMovement): bool
    {
        return false;
    }
}
