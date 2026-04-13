<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Warehouse;

class WarehousePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, Warehouse $warehouse): bool
    {
        if ($user->hasRole('admin')) {
            return true;
        }

        if (! $user->hasAnyRole(['produccion', 'comercial'])) {
            return false;
        }

        return $user->warehouses()->where('warehouses.id', $warehouse->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Warehouse $warehouse): bool
    {
        return $user->hasRole('admin');
    }
}
