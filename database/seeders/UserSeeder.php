<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Usuarios fijos (originales) ---
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

        $produccion = User::firstOrCreate(
            ['email' => 'pintech.auxiliar@gmail.com'],
            [
                'name' => 'Auxiliar Producción',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => true,
                'last_login_at' => now()->subHours(2),
            ]
        );
        $produccion->assignRole('produccion');

        $comercial = User::firstOrCreate(
            ['email' => 'pintech.comercial@gmail.com'],
            [
                'name' => 'Gerente Comercial',
                'password' => Hash::make('Pintech_2026'),
                'email_verified_at' => now(),
                'is_active' => false,
                'last_login_at' => now()->subDays(3),
            ]
        );
        $comercial->assignRole('comercial');

        // --- Usuarios adicionales para probar paginación (97 más) ---
        // Lista de roles disponibles (ajusta según tus roles reales)
        $roles = ['admin', 'produccion', 'comercial', 'supervisor', 'invitado'];

        for ($i = 1; $i <= 97; $i++) {
            $name = fake()->name();
            $email = fake()->unique()->safeEmail();

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make('password'), // Contraseña genérica
                'email_verified_at' => now(),
                'is_active' => (bool) rand(0, 1),
                'last_login_at' => now()->subDays(rand(0, 30)),
                'created_at' => now()->subDays(rand(0, 60)),
                'updated_at' => now(),
            ]);

            // Asignar un rol aleatorio
            $randomRole = $roles = ['admin', 'produccion', 'comercial'];
            $user->assignRole($randomRole);
        }

        // Opcional: si quieres exactamente 100 usuarios y ya tenías 3,
        // con 97 llegas a 100. Si tu factory existe y quieres más,
        // puedes cambiar el límite o usar User::factory(97)->create()
        // pero necesitarías definir el factory con los roles.
    }
}