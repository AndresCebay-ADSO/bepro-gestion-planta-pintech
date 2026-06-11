<?php

namespace App\Policies;

use App\Models\Alert;
use App\Models\User;

class AlertPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function view(User $user, Alert $alert): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function resolve(User $user, Alert $alert): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }
}
