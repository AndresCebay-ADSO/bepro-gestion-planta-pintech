<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Supplier;
use App\Models\User;

/**
 * Proveedores (docs/MATRIZ_RBAC.md §3, Catálogos). Viven en Configuración → Catálogos: se ven con `catalogs.view`, pero se
 * gestionan con un permiso propio, porque los permisos de escritura de catálogos son exclusivos de SuperAdmin y quien
 * registra las entradas de materia prima es el Admin.
 */
class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::CatalogsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::SuppliersManage->value);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersManage->value);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->can(Permission::SuppliersManage->value);
    }
}
