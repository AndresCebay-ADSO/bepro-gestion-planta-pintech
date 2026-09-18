<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\FinishedInventoryMovement;
use App\Models\User;

class FinishedInventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FinishedInventoryMovementsView->value);
    }

    public function view(User $user, FinishedInventoryMovement $movement): bool
    {
        return $user->can(Permission::FinishedInventoryMovementsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FinishedInventoryMovementsCreate->value);
    }

    // Los movimientos de producto terminado son inmutables (docs/MATRIZ_RBAC.md, principio 5).

    public function update(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }
}
