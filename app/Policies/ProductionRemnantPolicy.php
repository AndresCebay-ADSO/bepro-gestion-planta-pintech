<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\ProductionRemnant;
use App\Models\User;

class ProductionRemnantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'operador']);
    }

    public function view(User $user, ProductionRemnant $remnant): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'operador']);
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion']);
    }
}
