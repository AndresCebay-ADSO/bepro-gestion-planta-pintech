<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitsOfMeasureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $units = [
            // Peso
            [
                'code' => 'kg',
                'name' => 'Kilogramo',
                'symbol' => 'kg',
                'description' => 'Unidad de peso',
                'to_kg_conversion' => 1,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'g',
                'name' => 'Gramo',
                'symbol' => 'g',
                'description' => 'Unidad de peso',
                'to_kg_conversion' => 0.001,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'mg',
                'name' => 'Miligramo',
                'symbol' => 'mg',
                'description' => 'Unidad de peso',
                'to_kg_conversion' => 0.000001,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'lb',
                'name' => 'Libra',
                'symbol' => 'lb',
                'description' => 'Unidad de peso (avoirdupois)',
                'to_kg_conversion' => 0.453592,
                'to_liter_conversion' => null,
            ],
            // Volumen
            [
                'code' => 'lt',
                'name' => 'Litro',
                'symbol' => 'L',
                'description' => 'Unidad de volumen',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 1,
            ],
            [
                'code' => 'ml',
                'name' => 'Mililitro',
                'symbol' => 'mL',
                'description' => 'Unidad de volumen',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.001,
            ],
            [
                'code' => 'gal',
                'name' => 'Galón US',
                'symbol' => 'gal',
                'description' => 'Unidad de volumen (US)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 3.78541,
            ],
            [
                'code' => 'gal_imp',
                'name' => 'Galón Imperial',
                'symbol' => 'imp gal',
                'description' => 'Unidad de volumen (Imperial)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 4.54609,
            ],
            [
                'code' => 'bbl',
                'name' => 'Barril',
                'symbol' => 'bbl',
                'description' => 'Unidad de volumen (industria)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 158.987,
            ],
            // Otros
            [
                'code' => 'u',
                'name' => 'Unidad',
                'symbol' => 'u',
                'description' => 'Unidad sin medida específica',
                'to_kg_conversion' => null,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'm3',
                'name' => 'Metro cúbico',
                'symbol' => 'm³',
                'description' => 'Unidad de volumen',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 1000,
            ],
        ];

        foreach ($units as $unit) {
            DB::table('unit_of_measures')->updateOrInsert(
                ['code' => $unit['code']],
                array_merge($unit, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
