<?php

namespace App\Policies;

use App\Models\Formula;
use App\Models\User;

class FormulaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function view(User $user, Formula $formula): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, Formula $formula): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, Formula $formula): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, Formula $formula): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, Formula $formula): bool
    {
        return $user->hasRole('admin');
    }
}
