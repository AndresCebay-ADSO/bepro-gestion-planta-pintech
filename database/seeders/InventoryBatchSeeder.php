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
        $cali = Warehouse::where('name', 'Planta Cali')->first();

        if (! $cali) {
            $this->command->error('Warehouse Planta Cali not found. Run WarehouseSeeder first.');

            return;
        }

        // Configuración de lotes específicos: [cantidad, precio_unitario]
        $batchesConfig = [
            'S - 7 R' => [
                ['qty' => 50, 'price' => 1500],
                ['qty' => 120, 'price' => 2000],
                ['qty' => 164, 'price' => 1700],
            ],
            'S - 11 R' => [
                ['qty' => 9, 'price' => 2000],
                ['qty' => 7, 'price' => 1000],
                ['qty' => 43, 'price' => 1500],
            ],
            'S - 4' => [
                ['qty' => 100, 'price' => 1800],
                ['qty' => 100, 'price' => 2000],
                ['qty' => 380, 'price' => 2200],
            ],
            'S - 5' => [
                ['qty' => 76, 'price' => 1800],
                ['qty' => 10, 'price' => 2000],
                ['qty' => 90, 'price' => 2300],
            ],
            'ENV-P-BI5' => [
                ['qty' => 10, 'price' => 20000],
                ['qty' => 7, 'price' => 25000],
                ['qty' => 50, 'price' => 21000],
            ],
        ];

        foreach ($batchesConfig as $code => $batches) {
            $material = RawMaterial::where('code', $code)->first();

            if (! $material) {
                $this->command->warn("Raw material with code '{$code}' not found.");

                continue;
            }

            foreach ($batches as $index => $batch) {
                // Fechas en orden cronológico: lote 1 más antiguo, lote 3 más reciente
                $daysAgo = 60 - ($index * 15) + rand(0, 10);
                $entryDate = now()->subDays($daysAgo);
                $expiryDate = now()->addDays(rand(180, 720));

                InventoryBatch::create([
                    'raw_material_id' => $material->id,
                    'warehouse_id' => $cali->id,
                    'initial_quantity' => $batch['qty'],
                    'remaining_quantity' => $batch['qty'],
                    'unit_price' => $batch['price'],
                    'entry_date' => $entryDate,
                    'expiry_date' => $expiryDate,
                    'lot_number' => 'LOTE-'.$entryDate->format('Y-m').'-'.str_pad($material->id, 4, '0', STR_PAD_LEFT).'-'.($index + 1),
                    'supplier' => fake('es_CO')->company(),
                ]);
            }
        }

        $this->command->info('Inventory batches seeded successfully.');
    }
}
