<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShrinkWrapType;
use App\Models\User;

/**
 * Tipos de termoencogido (docs/MATRIZ_RBAC.md §3, Termoencogido). Viven en Configuración → Catálogos: se ven con
 * `catalogs.view`, pero se gestionan con un permiso propio, porque los permisos de escritura de catálogos son exclusivos de
 * SuperAdmin y estos los gestiona también el Admin.
 */
class ShrinkWrapTypePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CatalogsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ShrinkWrapTypesManage->value);
    }

    public function update(User $user, ShrinkWrapType $shrinkWrapType): bool
    {
        return $user->can(Permission::ShrinkWrapTypesManage->value);
    }

    public function delete(User $user, ShrinkWrapType $shrinkWrapType): bool
    {
        return $user->can(Permission::ShrinkWrapTypesManage->value);
    }
}
