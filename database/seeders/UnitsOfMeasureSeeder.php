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
            // Peso - Solo las unidades que usa la empresa
            [
                'code' => 'gr',
                'name' => 'Gramo',
                'symbol' => 'gr',
                'description' => 'Unidad de peso para pequeñas cantidades (pigmentos, aditivos)',
                'to_kg_conversion' => 0.001,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'kg',
                'name' => 'Kilogramo',
                'symbol' => 'kg',
                'description' => 'Unidad de peso base para materias primas (resinas, pigmentos)',
                'to_kg_conversion' => 1,
                'to_liter_conversion' => null,
            ],
            // Volumen - Solo las unidades que usa la empresa
            [
                'code' => 'l',
                'name' => 'Litro',
                'symbol' => 'L',
                'description' => 'Unidad de volumen base para líquidos (solventes, catalizadores)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 1,
            ],
            [
                'code' => 'gl',
                'name' => 'Galón',
                'symbol' => 'gl',
                'description' => 'Galón estadounidense (3.785 L) - para compras/importaciones',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 3.7854,
            ],
            // Conteo - Para insumos físicos
            [
                'code' => 'u',
                'name' => 'Unidad',
                'symbol' => 'u',
                'description' => 'Unidad para conteo (envases, tapas, asas, empaques)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => null,
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
