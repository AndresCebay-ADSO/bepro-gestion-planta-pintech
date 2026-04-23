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
            RawMaterialSeeder::class,         // Materias primas
            ProductCategorySeeder::class,      // Categorías productos
            WarehouseSeeder::class,            // Bodegas
        ]);

        // Seeders de datos de negocio y operación
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            WarehouseUserSeeder::class,
            InventoryBatchSeeder::class,
            ProductSeeder::class,
        ]);
    }
}
