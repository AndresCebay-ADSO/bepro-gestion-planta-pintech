<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Seeder for RawMaterial model.
 * Handles initial setup and demo data for pagination.
 */
class RawMaterialSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Ensure necessary units of measure exist
        $kgUnit = UnitOfMeasure::where('code', 'kg')->first();
        $literUnit = UnitOfMeasure::where('code', 'l')->first();
        $unitUnit = UnitOfMeasure::where('code', 'u')->first();

        // --- 1. Original raw materials (Chemicals) ---
        $items = [
            ['code' => 'MP001', 'unit' => 'kg', 'price' => 18.5000, 'name' => 'Pigmento Blanco'],
            ['code' => 'RES01', 'unit' => 'kg', 'price' => 24.9000, 'name' => 'Resina Acrílica'],
            ['code' => 'AC4', 'unit' => 'l', 'price' => 12.7500, 'name' => 'Ajustador de Viscosidad'],
        ];

        foreach ($items as $item) {
            $unit = UnitOfMeasure::where('code', $item['unit'])->first();
            RawMaterial::updateOrCreate(
                ['code' => $item['code']],
                [
                    'unit_of_measure_id' => $unit->id,
                    'current_price' => $item['price'],
                    'previous_price' => null,
                    'minimum_stock' => 0,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]
            );
        }

        // --- 2. Packaging Materials (Containers) ---
        $packaging = [
            ['code' => 'ENV-GL-1GL', 'price' => 2500, 'name' => 'Envase Galón Plástico'],
            ['code' => 'ENV-BI-5GL', 'price' => 8500, 'name' => 'Envase Bidón 5gl'],
            ['code' => 'ENV-BA-2.5GL', 'price' => 5500, 'name' => 'Envase Balde 2.5gl'],
            ['code' => 'ENV-TA-50GL', 'price' => 45000, 'name' => 'Envase Tambor 50gl'],
            ['code' => 'ENV-CU-15L', 'price' => 7800, 'name' => 'Envase Cuñete 15L'],
            ['code' => 'ENV-GL-1/4', 'price' => 1200, 'name' => 'Envase 1/4 Galón'],
            ['code' => 'ENV-GL-1/16', 'price' => 800, 'name' => 'Envase 1/16 Galón'],
        ];

        foreach ($packaging as $pack) {
            RawMaterial::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => $pack['price'],
                    'previous_price' => null,
                    'minimum_stock' => 100,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        // --- 3. Additional raw materials for pagination testing ---
        $units = ['kg', 'l'];

        for ($i = 1; $i <= 50; $i++) {
            $unitCode = $units[array_rand($units)];
            $unitId = ($unitCode === 'kg') ? $kgUnit->id : $literUnit->id;

            $code = 'MP'.str_pad($i + 100, 4, '0', STR_PAD_LEFT); 

            RawMaterial::updateOrCreate(
                ['code' => $code],
                [
                    'unit_of_measure_id' => $unitId,
                    'current_price' => fake()->randomFloat(4, 5, 150),
                    'previous_price' => fake()->optional(0.3)->randomFloat(4, 5, 150),
                    'minimum_stock' => fake()->numberBetween(0, 200),
                    'alert_days_before_expiry' => fake()->numberBetween(15, 180),
                    'is_active' => fake()->boolean(85),
                    'created_at' => now()->subDays(rand(0, 60)),
                    'updated_at' => now(),
                ]
            );
        }

        $this->command->info('Created/Updated '.RawMaterial::count().' raw materials (including packaging).');
    }
}
