<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

class RawMaterialSeeder extends Seeder
{
    public function run(): void
    {
        // Asegurar que existan las unidades de medida necesarias
        $kgUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'kg'],
            ['name' => 'Kilogramo', 'symbol' => 'kg', 'is_active' => true]
        );
        $literUnit = UnitOfMeasure::firstOrCreate(
            ['code' => 'l'],
            ['name' => 'Litro', 'symbol' => 'L', 'is_active' => true]
        );

        // --- 3 materias primas originales ---
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

        // --- 97 materias primas adicionales para paginación ---
        $units = ['kg', 'l'];
        $unitIds = [
            'kg' => $kgUnit->id,
            'l' => $literUnit->id,
        ];

        for ($i = 1; $i <= 97; $i++) {
            $unitType = $units[array_rand($units)];
            $unitId = $unitIds[$unitType];

            $code = 'MP'.str_pad($i + 100, 4, '0', STR_PAD_LEFT); // MP0101..MP0197

            RawMaterial::create([
                'code' => $code,
                'unit_of_measure_id' => $unitId,
                'current_price' => fake()->randomFloat(4, 5, 150),
                'previous_price' => fake()->optional(0.3)->randomFloat(4, 5, 150),
                'minimum_stock' => fake()->numberBetween(0, 200),
                'alert_days_before_expiry' => fake()->numberBetween(15, 180),
                'is_active' => fake()->boolean(85),
                'created_at' => now()->subDays(rand(0, 60)),
                'updated_at' => now(),
            ]);
        }

        $this->command->info('Se crearon '.RawMaterial::count().' materias primas (incluye kg y l).');
    }
}
