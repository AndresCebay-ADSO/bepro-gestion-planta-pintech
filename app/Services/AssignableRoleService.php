<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Roles que un usuario puede asignar a otros (docs/MATRIZ_RBAC.md §8.4).
 *
 * Evita la escalada de privilegios: nadie asigna un rol con permisos que él mismo no tiene. El rol super-admin tiene
 * todos los permisos, así que solo lo asigna otro SuperAdmin.
 */
class AssignableRoleService
{
    /**
     * @return Collection<int, Role>
     */
    public function for(User $actor): Collection
    {
        $roles = Role::query()
            ->where('guard_name', SystemRole::GUARD)
            ->with('permissions:id,name')
            ->orderBy('id')
            ->get();

        return $roles
            ->filter(fn (Role $role): bool => $actor->holdsAllPermissions($role->permissions->pluck('name')))
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
