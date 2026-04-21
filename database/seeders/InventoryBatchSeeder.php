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
        $neiva = Warehouse::where('name', 'Bodega Neiva')->first();

        if (! $cali || ! $neiva) {
            $this->command->error('Warehouses not found. Run WarehouseSeeder first.');
            return;
        }

        $rawMaterials = RawMaterial::all();

        foreach ($rawMaterials as $material) {
            // Decidir cuántos lotes crear para este material (1 a 3)
            $batchCount = rand(1, 3);

            for ($i = 0; $i < $batchCount; $i++) {
                $daysAgo = rand(0, 45);
                $entryDate = now()->subDays($daysAgo);
                
                // 10% de probabilidad de que el material esté próximo a vencer (o ya vencido) para pruebas
                $isExpiring = (rand(1, 10) === 1);
                $expiryDate = $isExpiring 
                    ? now()->addDays(rand(-5, 15)) 
                    : now()->addDays(rand(180, 720));

                $initialQty = rand(20, 500);
                // Si es un lote viejo (más de 20 días), el stock restante debería ser menor
                $remainingQty = ($daysAgo > 20) ? rand(0, (int)($initialQty * 0.4)) : rand((int)($initialQty * 0.5), $initialQty);

                // Toda la materia prima se concentra en la Planta Cali (Fábrica) según especificación
                $warehouseId = $cali->id;

                InventoryBatch::create([
                    'raw_material_id' => $material->id,
                    'warehouse_id' => $warehouseId,
                    'initial_quantity' => $initialQty,
                    'remaining_quantity' => $remainingQty,
                    'unit_price' => $material->current_price ?? 5000,
                    'entry_date' => $entryDate,
                    'expiry_date' => $expiryDate,
                    'lot_number' => 'LOTE-' . $entryDate->format('Y-m') . '-' . str_pad($material->id, 4, '0', STR_PAD_LEFT),
                    'supplier' => fake('es_CO')->company(),
                ]);
            }
        }

        $this->command->info('Inventory batches seeded successfully.');
    }
}
