<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinishedInventoryMovement;
use App\Models\User;

class FinishedInventoryMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function view(User $user, FinishedInventoryMovement $movement): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }

    public function delete(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }

    public function restore(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }

    public function forceDelete(User $user, FinishedInventoryMovement $movement): bool
    {
        return false;
    }
}
