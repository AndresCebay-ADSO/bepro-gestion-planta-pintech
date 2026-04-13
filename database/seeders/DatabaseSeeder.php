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
        // Seeders de datos maestros (sin dependencias)
        $this->call([
            UnitsOfMeasureSeeder::class,
            RawMaterialSeeder::class,
        ]);

        // Seeders de roles y usuarios
        $this->call([
            RolePermissionSeeder::class,
            UserSeeder::class,
            WarehouseSeeder::class,
            WarehouseUserSeeder::class,
            InventoryBatchSeeder::class,
        ]);
    }
}
