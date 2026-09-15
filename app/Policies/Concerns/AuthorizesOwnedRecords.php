<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Enums\Permission;
use App\Models\User;

/**
 * Registros con dueño (docs/MATRIZ_RBAC.md §1): `view_all` ve todos; `view_own` solo los propios (`created_by`).
 * Las acciones sobre registros ajenos exigen además `view_all`.
 */
trait AuthorizesOwnedRecords
{
    protected function canAccessOwnedRecord(User $user, int|string|null $ownerId, Permission $viewAll, Permission $viewOwn): bool
    {
        if ($user->can($viewAll->value)) {
            return true;
        }

        return $user->can($viewOwn->value)
            && $ownerId !== null
            && (int) $ownerId === (int) $user->id;
    }
}
