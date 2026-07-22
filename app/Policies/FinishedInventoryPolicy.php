<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\FinishedInventory;
use App\Models\User;

class FinishedInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, FinishedInventory $finishedInventory): bool
    {
        if (! $user->hasAnyRole(['admin', 'produccion', 'comercial'])) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return $user->warehouses()
            ->where('warehouses.id', $finishedInventory->warehouse_id)
            ->exists();
    }
}
