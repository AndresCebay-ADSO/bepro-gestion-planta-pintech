<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductionRemnant;
use App\Models\User;

class ProductionRemnantPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ProductionRemnantsView->value);
    }

    public function view(User $user, ProductionRemnant $remnant): bool
    {
        return $user->can(Permission::ProductionRemnantsView->value);
    }
}
