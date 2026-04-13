<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder for User model.
 * Handles production users and demo accounts for pagination.
 */
class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Fixed Users (Original/Production) ---
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

        // --- Additional Demo Users (Local/Testing Environment Only) ---
        if (app()->environment(['local', 'testing'])) {
            $roles = ['admin', 'produccion', 'comercial'];

            for ($i = 1; $i <= 97; $i++) {
                $name = fake()->name();
                $email = fake()->unique()->safeEmail();

                $user = User::updateOrCreate(
                    ['email' => $email],
                    [
                        'name' => $name,
                        'password' => Hash::make('password'), // Generic password for local testing
                        'email_verified_at' => now(),
                        'is_active' => (bool) rand(0, 1),
                        'last_login_at' => now()->subDays(rand(0, 30)),
                        'created_at' => now()->subDays(rand(0, 60)),
                        'updated_at' => now(),
                    ]
                );

                // Correct random role assignment
                $randomRole = $roles[array_rand($roles)];
                $user->assignRole($randomRole);
            }

            $this->command->info('Created/Updated '.User::count().' users (including demo accounts).');
        }
    }
}
