<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'pintech.sistemas@gmail.com'],
            [
                'name' => 'Admin Sistemas',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subMinutes(12),
            ]
        );
        $admin->assignRole('admin');

        $production = User::firstOrCreate(
            ['email' => 'pintech.auxiliar@gmail.com'],
            [
                'name' => 'Auxiliar Producción',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(2),
            ]
        );
        $production->assignRole('produccion');

        $commercial = User::firstOrCreate(
            ['email' => 'pintech.comercial@gmail.com'],
            [
                'name' => 'Gerente Comercial',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => false,
                'last_login_at' => now()->subDays(3),
            ]
        );
        $commercial->assignRole('comercial');

        $operator = User::firstOrCreate(
            ['email' => 'pintech.operador@gmail.com'],
            [
                'name' => 'Operador Planta',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(1),
            ]
        );
        $operator->assignRole('operador');

        $this->command->info('Created/Updated 4 users.');
    }
}
