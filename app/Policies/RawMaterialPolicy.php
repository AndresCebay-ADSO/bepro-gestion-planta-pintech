<?php

namespace App\Policies;

use App\Models\RawMaterial;
use App\Models\User;

class RawMaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, RawMaterial $rawMaterial): bool
    {
        return $user->hasRole('admin');
    }
}
