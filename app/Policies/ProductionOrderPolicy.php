<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function view(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function update(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }

    public function delete(User $user, ProductionOrder $productionOrder): bool
    {
        return $user->hasRole('admin');
    }
}
