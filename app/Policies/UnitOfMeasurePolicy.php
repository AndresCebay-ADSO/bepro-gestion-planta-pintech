<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\UnitOfMeasure;
use App\Models\User;

/**
 * Catálogo de unidades de medida (docs/MATRIZ_RBAC.md §3, Catálogos). Los `<select>` de los formularios que eligen
 * una unidad no pasan por aquí: no exigen `catalogs.view`.
 */
class UnitOfMeasurePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CatalogsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::CatalogsCreate->value);
    }

    public function update(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can(Permission::CatalogsEdit->value);
    }

    public function delete(User $user, UnitOfMeasure $unitOfMeasure): bool
    {
        return $user->can(Permission::CatalogsDelete->value);
    }
}
