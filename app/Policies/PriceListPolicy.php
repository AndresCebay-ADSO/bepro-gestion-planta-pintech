<?php

namespace App\Policies;

use App\Models\PriceList;
use App\Models\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, PriceList $priceList): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, PriceList $priceList): bool
    {
        return $user->hasRole('admin');
    }
}
