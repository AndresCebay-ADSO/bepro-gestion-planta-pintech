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
        $warehouses = [
            [
                'name' => 'Planta Cali',
                'city' => 'Cali',
                'address' => 'Sede Principal Cali',
                'type' => 'factory',
                'is_active' => true,
            ],
            [
                'name' => 'Bodega Neiva',
                'city' => 'Neiva',
                'address' => 'Sede Neiva',
                'type' => 'storage',
                'is_active' => true,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            Warehouse::updateOrCreate(
                ['name' => $warehouse['name']],
                $warehouse
            );
        }
    }
}
