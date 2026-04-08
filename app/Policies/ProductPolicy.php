<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Product $product): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Product $product): bool
    {
        return $user->hasRole('admin');
    }
}
