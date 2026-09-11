<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Formula;
use App\Models\User;

class FormulaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::FormulasView->value);
    }

    public function view(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::FormulasCreate->value);
    }

    public function update(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasEdit->value);
    }

    public function activate(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasActivate->value);
    }

    public function delete(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasDelete->value);
    }

    public function restore(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasDelete->value);
    }

    public function forceDelete(User $user, Formula $formula): bool
    {
        return $user->can(Permission::FormulasDelete->value);
    }
}
