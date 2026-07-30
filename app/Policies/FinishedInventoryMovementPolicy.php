<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\FinishedInventoryMovementReason;
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
        if ($movement->production_order_id !== null) {
            return false;
        }

        if ($movement->reason === FinishedInventoryMovementReason::Transfer) {
            return false;
        }

        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, FinishedInventoryMovement $movement): bool
    {
        if ($movement->production_order_id !== null) {
            return false;
        }

        return $user->hasRole('admin');
    }

    public function restore(User $user, FinishedInventoryMovement $movement): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, FinishedInventoryMovement $movement): bool
    {
        return $user->hasRole('admin');
    }
}
