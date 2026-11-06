<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Crea el primer usuario de un entorno nuevo (staging o producción), donde los usuarios no se siembran. Pide los datos
 * por la consola, nunca por argumentos: la contraseña no queda en el historial del servidor. Valida con las mismas
 * reglas que la pantalla de usuarios, y en esos entornos la contraseña sigue las reglas exigentes.
 */
#[Signature('users:create-admin')]
#[Description('Crea un usuario SuperAdmin (soporte técnico) pidiendo nombre, correo y contraseña')]
class CreateAdminUserCommand extends Command
{
    use PasswordValidationRules, ProfileValidationRules;

    public function handle(): int
    {
        $role = Role::query()
            ->where('name', SystemRole::SuperAdmin->value)
            ->where('guard_name', SystemRole::GUARD)
            ->first();

        if ($role === null) {
            $this->error('El rol super-admin no existe. Ejecuta primero: php artisan db:seed --class=RolePermissionSeeder --force');

            return self::FAILURE;
        }

        $data = [
            'name' => trim((string) $this->ask('Nombre completo')),
            'email' => Str::lower(trim((string) $this->ask('Correo'))),
            'password' => (string) $this->secret('Contraseña'),
            'password_confirmation' => (string) $this->secret('Repite la contraseña'),
        ];

        $validator = Validator::make($data, [
            'name' => $this->nameRules(),
            'email' => $this->emailRules(),
            'password' => $this->passwordRules(),
        ], attributes: [
            'name' => 'nombre',
            'email' => 'correo',
            'password' => 'contraseña',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            $this->warn('No se creó ningún usuario. Vuelve a correr el comando.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data, $role): User {
            // `email_verified_at` no es asignable en masa: `create` lo descartaría sin avisar.
            $user = (new User)->forceFill([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'email_verified_at' => now(),
                'is_active' => true,
            ]);
            $user->save();

            $user->assignRole($role);

            activity('security')
                ->performedOn($user)
                ->event('role_changed')
                ->withProperties([
                    'old_role' => null,
                    'new_role' => SystemRole::SuperAdmin->value,
                    'source' => 'console',
                ])
                ->log('Usuario creado por consola con el rol '.SystemRole::SuperAdmin->label());

            return $user;
        });

        $this->info("Usuario {$user->name} <{$user->email}> creado como SuperAdmin.");

        return self::SUCCESS;
    }
}
