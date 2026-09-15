<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::AlertsView->value);
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->can(Permission::AlertsView->value);
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $user->can(Permission::AlertsResolve->value);
    }
}
