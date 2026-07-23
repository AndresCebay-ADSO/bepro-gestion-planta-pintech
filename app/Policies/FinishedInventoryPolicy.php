<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class FinishedInventoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'produccion', 'comercial']);
    }
}
