<?php

namespace Database\Seeders;

use App\Enums\SystemRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('UserSeeder omitido en entornos de producción o compartidos.');

            return;
        }

        $defaultPassword = config('app.default_user_password', env('SEED_USER_PASSWORD', 'Pintech_2026'));

        // Usuario de soporte técnico con acceso total. En producción se otorga con `users:grant-super-admin`.
        $superAdmin = User::firstOrCreate(
            ['email' => 'soporte@pintech.test'],
            [
                'name' => 'Soporte Técnico',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subMinutes(30),
            ]
        );
        $superAdmin->assignRole(SystemRole::SuperAdmin->value);

        $admin = User::firstOrCreate(
            ['email' => 'pintech.sistemas@gmail.com'],
            [
                'name' => 'Admin Sistemas',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subMinutes(12),
            ]
        );
        $admin->assignRole(SystemRole::Admin->value);

        $production = User::firstOrCreate(
            ['email' => 'pintech.auxiliar@gmail.com'],
            [
                'name' => 'Auxiliar Producción',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(2),
            ]
        );
        $production->assignRole(SystemRole::Production->value);

        $commercial = User::firstOrCreate(
            ['email' => 'pintech.comercial@gmail.com'],
            [
                'name' => 'Gerente Comercial',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
                'is_active' => false,
                'last_login_at' => now()->subDays(3),
            ]
        );
        $commercial->assignRole(SystemRole::Commercial->value);

        $operator = User::firstOrCreate(
            ['email' => 'pintech.operador@gmail.com'],
            [
                'name' => 'Operador Planta',
                'password' => Hash::make($defaultPassword),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(1),
            ]
        );
        $operator->assignRole(SystemRole::Operator->value);

        $this->command?->info('Created/Updated 5 users.');
    }
}
