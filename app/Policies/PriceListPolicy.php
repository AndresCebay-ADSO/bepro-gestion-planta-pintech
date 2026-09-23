<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PriceList;
use App\Models\User;

/**
 * Las listas de precios son históricas: las genera el recálculo de costos y nadie las edita a mano.
 */
class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::PriceListsView->value);
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->can(Permission::PriceListsView->value);
    }
}
