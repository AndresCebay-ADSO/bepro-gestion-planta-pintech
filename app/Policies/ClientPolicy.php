<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->hasRole('admin');
    }
}
