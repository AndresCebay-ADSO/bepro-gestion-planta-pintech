<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usuario 1: Admin - Sistemas
        $admin = User::firstOrCreate(
            ['email' => 'pintech.sistemas@gmail.com'],
            [
                'name' => 'Admin Sistemas',
                'email' => 'pintech.sistemas@gmail.com',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subMinutes(12),
            ]
        );
        $admin->assignRole('admin');

        // Usuario 2: Asistente de Producción
        $produccion = User::firstOrCreate(
            ['email' => 'pintech.auxiliar@gmail.com'],
            [
                'name' => 'Auxiliar Producción',
                'email' => 'pintech.auxiliar@gmail.com',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(2),
            ]
        );
        $produccion->assignRole('produccion');

        // Usuario 3: Comercial
        $comercial = User::firstOrCreate(
            ['email' => 'pintech.comercial@gmail.com'],
            [
                'name' => 'Gerente Comercial',
                'email' => 'pintech.comercial@gmail.com',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => false,
                'last_login_at' => now()->subDays(3),
            ]
        );
        $comercial->assignRole('comercial');
    }
}
