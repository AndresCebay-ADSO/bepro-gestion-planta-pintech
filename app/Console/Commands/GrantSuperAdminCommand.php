<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\Permission\Models\Role;

#[Signature('users:grant-super-admin {email : Correo del usuario que recibirá el rol SuperAdmin} {--force : Asignar sin pedir confirmación}')]
#[Description('Asigna el rol SuperAdmin (soporte técnico) a un usuario existente y activo')]
class GrantSuperAdminCommand extends Command
{
    public function handle(): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if ($user === null) {
            $this->error("No existe un usuario con el correo {$email}.");

            return self::FAILURE;
        }

        if (! $user->is_active) {
            $this->error('El usuario está inactivo. Actívalo antes de otorgarle el rol SuperAdmin.');

            return self::FAILURE;
        }

        $role = Role::query()
            ->where('name', SystemRole::SuperAdmin->value)
            ->where('guard_name', 'web')
            ->first();

        if ($role === null) {
            $this->error('El rol super-admin no existe. Ejecuta primero: php artisan db:seed --class=RolePermissionSeeder');

            return self::FAILURE;
        }

        if ($user->isSuperAdmin()) {
            $this->info("{$user->name} ya es SuperAdmin.");

            return self::SUCCESS;
        }

        $oldRole = $user->getRoleNames()->first() ?? 'none';

        $confirmed = $this->option('force') || $this->confirm(
            "¿Asignar SuperAdmin a {$user->name} <{$user->email}>? Reemplaza su rol actual ({$oldRole})."
        );

        if (! $confirmed) {
            $this->warn('Operación cancelada.');

            return self::FAILURE;
        }

        // Un rol por usuario (docs/MATRIZ_RBAC.md §0): el rol SuperAdmin reemplaza al anterior.
        $user->syncRoles([$role]);

        activity('security')
            ->performedOn($user)
            ->event('role_changed')
            ->withProperties([
                'old_role' => $oldRole,
                'new_role' => SystemRole::SuperAdmin->value,
                'source' => 'console',
            ])
            ->log('Rol de usuario modificado de '.$oldRole.' a '.SystemRole::SuperAdmin->value);

        $this->info("{$user->name} ahora es SuperAdmin.");

        return self::SUCCESS;
    }
}
