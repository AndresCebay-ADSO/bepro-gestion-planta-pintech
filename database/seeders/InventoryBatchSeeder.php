<?php

namespace Database\Seeders;

use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class InventoryBatchSeeder extends Seeder
{
    public function run(): void
    {
        $cali = Warehouse::where('city', 'Cali')->where('type', 'factory')->first();

        if (! $cali) {
            return;
        }

        $rawMaterials = RawMaterial::all();

        foreach ($rawMaterials as $material) {
            InventoryBatch::create([
                'raw_material_id' => $material->id,
                'warehouse_id' => $cali->id,
                'initial_quantity' => 100,
                'remaining_quantity' => 100,
                'unit_price' => $material->current_price ?? 10.00,
                'entry_date' => now(),
                'lot_number' => 'LOT-'.strtoupper(substr(uniqid(), -5)),
                'supplier' => 'Proveedor General S.A.',
            ]);
        }
    }
}
