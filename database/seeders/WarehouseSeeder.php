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
            ['name' => 'Bodega Neiva'],
            [
                'city' => 'Neiva',
                'address' => 'Zona Industrial, Neiva',
                'type' => 'bodega',
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['name' => 'Planta Cali'],
            [
                'city' => 'Cali',
                'address' => 'Parque Industrial, Cali',
                'type' => 'fabrica',
                'is_active' => true,
            ]
        );

        Warehouse::updateOrCreate(
            ['name' => 'Depósito Auxiliar'],
            [
                'city' => 'Neiva',
                'address' => 'Bodega Satélite, Neiva',
                'type' => 'bodega',
                'is_active' => true,
            ]
        );
    }
}
