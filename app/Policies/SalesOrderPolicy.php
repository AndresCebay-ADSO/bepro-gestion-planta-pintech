<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;

class SalesOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial', 'produccion']);
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion'])
            || ($user->hasRole('comercial') && $salesOrder->created_by === $user->id);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'comercial']);
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $user->hasRole('admin');
    }
}
