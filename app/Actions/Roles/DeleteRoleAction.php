<?php

declare(strict_types=1);

namespace App\Actions\Roles;

use App\Enums\SystemRole;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

class DeleteRoleAction
{
    /**
     * Elimina un rol personalizado sin usuarios. La tabla `model_has_roles` borra en cascada: eliminar un rol con
     * usuarios los dejaría sin acceso a nada, así que se niega.
     *
     * @throws \DomainException
     */
    public function execute(Role $role): void
    {
        DB::transaction(function () use ($role): void {
            // El bloqueo serializa el borrado con las asignaciones de UserController, que bloquean la misma fila.
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->id);

            if (SystemRole::isSystem($lockedRole->name)) {
                throw new \DomainException('Los roles del sistema no se pueden eliminar.');
            }

            $usersCount = $lockedRole->users()->count();

            if ($usersCount > 0) {
                throw new \DomainException(
                    "No se puede eliminar el rol {$lockedRole->name}: tiene {$usersCount} usuario(s) asignado(s). Asígnales otro rol primero."
                );
            }

            $permissions = $lockedRole->permissions->pluck('name')->sort()->values()->all();

            $lockedRole->delete();

            activity('security')
                ->performedOn($lockedRole)
                ->event('role_deleted')
                ->withProperties(['name' => $lockedRole->name, 'permissions' => $permissions])
                ->log("Rol {$lockedRole->name} eliminado");
        });
    }
}
