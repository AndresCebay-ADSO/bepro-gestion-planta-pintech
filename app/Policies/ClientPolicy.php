<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Client;
use App\Models\User;

class ClientPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ClientsView->value);
    }

    public function view(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ClientsCreate->value);
    }

    public function update(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsEdit->value);
    }

    public function delete(User $user, Client $client): bool
    {
        return $user->can(Permission::ClientsDelete->value);
    }
}
