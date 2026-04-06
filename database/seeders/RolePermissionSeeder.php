<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear roles del sistema
        Role::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'web'],
            ['name' => 'admin', 'guard_name' => 'web']
        );

        Role::firstOrCreate(
            ['name' => 'produccion', 'guard_name' => 'web'],
            ['name' => 'produccion', 'guard_name' => 'web']
        );

        Role::firstOrCreate(
            ['name' => 'comercial', 'guard_name' => 'web'],
            ['name' => 'comercial', 'guard_name' => 'web']
        );

        // Nota: Los permisos específicos se definen por operación en Controllers/Middleware
        // Por ahora solo se crean los roles base
    }
}
