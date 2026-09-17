<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Roles que un usuario puede asignar a otros (docs/PLAN_FASE_2_RBAC.md, 2.4).
 *
 * Evita la escalada de privilegios: salvo un SuperAdmin, nadie asigna un rol con permisos que él mismo no tiene,
 * y el rol super-admin solo lo asigna un SuperAdmin.
 */
class AssignableRoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function for(User $actor): Collection
    {
        $roles = Role::query()
            ->where('guard_name', 'web')
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get();

        if ($actor->isSuperAdmin()) {
            return $roles;
        }

        $actorPermissions = $actor->getAllPermissions()->pluck('name');

        return $roles
            ->reject(fn (Role $role): bool => $role->name === SystemRole::SuperAdmin->value)
            ->filter(fn (Role $role): bool => $role->permissions->pluck('name')->diff($actorPermissions)->isEmpty())
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function namesFor(User $actor): array
    {
        return $this->for($actor)->pluck('name')->all();
    }
}
