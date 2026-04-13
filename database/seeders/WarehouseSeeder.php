<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Warehouse::updateOrCreate(
            ['name' => 'Planta Neiva'],
            [
                'city' => 'Neiva',
                'address' => 'Zona Industrial, Neiva',
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['name' => 'Planta Cali'],
            [
                'city' => 'Cali',
                'address' => 'Parque Industrial, Cali',
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['name' => 'Depósito Auxiliar'],
            [
                'city' => 'Neiva',
                'address' => 'Bodega Satélite, Neiva',
                'is_active' => true,
            ]
        );
    }
}
