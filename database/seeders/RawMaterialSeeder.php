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
        $kgUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'kg'],
            ['name' => 'Kilogramo', 'symbol' => 'kg', 'is_active' => true]
        );
        $literUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'l'],
            ['name' => 'Litro', 'symbol' => 'L', 'is_active' => true]
        );

        // --- 3 Original raw materials ---
        $items = [
            ['code' => 'MP001', 'unit' => 'kg', 'price' => 18.5000],
            ['code' => 'RES01', 'unit' => 'kg', 'price' => 24.9000],
            ['code' => 'AC4', 'unit' => 'l', 'price' => 12.7500],
        ];

        foreach ($items as $item) {
            $unitId = ($item['unit'] === 'kg') ? $kgUnit->id : $literUnit->id;
            RawMaterial::updateOrCreate(
                ['code' => $item['code']],
                [
                    'unit_of_measure_id' => $unitId,
                    'current_price' => $item['price'],
                    'previous_price' => null,
                    'minimum_stock' => 0,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]
            );
        }

        // --- 97 additional raw materials for pagination testing ---
        $units = ['kg', 'l'];
        $unitIds = [
            'kg' => $kgUnit->id,
            'l' => $literUnit->id,
        ];

        for ($i = 1; $i <= 97; $i++) {
            $unitType = $units[array_rand($units)];
            $unitId = $unitIds[$unitType];

            $code = 'MP'.str_pad($i + 100, 4, '0', STR_PAD_LEFT); // MP0101..MP0197

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

        $this->command->info('Created/Updated ' . RawMaterial::count() . ' raw materials.');
    }
}
