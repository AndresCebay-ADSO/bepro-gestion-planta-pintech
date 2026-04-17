<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seeders de configuración base (requeridos)
        $this->call([
            UnitsOfMeasureSeeder::class,      // Unidades: gr, kg, l, gl, u
            RawMaterialCategorySeeder::class, // Categorías materia prima
            ProductCategorySeeder::class,      // Categorías productos
            WarehouseSeeder::class,            // Bodegas
        ]);

        // Seeders de usuarios y permisos
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            WarehouseUserSeeder::class,
        ]);

        // NOTA: Los siguientes seeders deben ejecutarse manualmente cuando
        // el usuario quiera crear datos de prueba:
        // - RawMaterialSeeder::class      // Materias primas
        // - ProductSeeder::class          // Productos base
        // - InventoryBatchSeeder::class   // Lotes de inventario
    }
}
