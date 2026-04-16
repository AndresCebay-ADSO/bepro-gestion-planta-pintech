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
                'description' => 'Unidad de peso base',
                'to_kg_conversion' => 1,
                'to_liter_conversion' => null,
            ],
            [
                'code' => 'g',
                'name' => 'Gramo',
                'symbol' => 'g',
                'description' => 'Unidad de peso fraccionaria',
                'to_kg_conversion' => 0.001,
                'to_liter_conversion' => null,
            ],
            // Volumen Base
            [
                'code' => 'l',
                'name' => 'Litro',
                'symbol' => 'L',
                'description' => 'Unidad de volumen base',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 1,
            ],
            [
                'code' => 'ml',
                'name' => 'Mililitro',
                'symbol' => 'mL',
                'description' => 'Unidad de volumen fraccionaria',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.001,
            ],
            [
                'code' => 'gal',
                'name' => 'Galón US',
                'symbol' => 'gal',
                'description' => 'Galón estándar (US)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 3.7854,
            ],
            // Fracciones de Galón
            [
                'code' => '1/4-gal',
                'name' => 'Cuarto de Galón',
                'symbol' => '1/4 gal',
                'description' => 'Pipa / Cuarto',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.9464,
            ],
            [
                'code' => '1/8-gal',
                'name' => 'Octavo de Galón',
                'symbol' => '1/8 gal',
                'description' => 'Octavo',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.4732,
            ],
            [
                'code' => '1/16-gal',
                'name' => 'Dieciseisavo de Galón',
                'symbol' => '1/16 gal',
                'description' => 'Pinta / 16vo',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.2366,
            ],
            [
                'code' => '1/32-gal',
                'name' => 'Treintaidosavo de Galón',
                'symbol' => '1/32 gal',
                'description' => 'Media pinta / 32vo',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.1183,
            ],
            [
                'code' => '1/64-gal',
                'name' => 'Sesentaicuatroavo de Galón',
                'symbol' => '1/64 gal',
                'description' => 'Onza fluida doble / 64vo',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 0.0591,
            ],
            // Presentaciones Industriales
            [
                'code' => 'cunete-3gl',
                'name' => 'Cuñete 3 Galones',
                'symbol' => '3 gal',
                'description' => 'Balde industrial 3gl',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 11.3562,
            ],
            [
                'code' => 'cunete-4gl',
                'name' => 'Cuñete 4 Galones',
                'symbol' => '4 gal',
                'description' => 'Balde industrial 4gl',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 15.1416,
            ],
            [
                'code' => 'cunete-5gl',
                'name' => 'Cuñete 5 Galones',
                'symbol' => '5 gal',
                'description' => 'Balde industrial 5gl (Estándar)',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 18.9271,
            ],
            [
                'code' => 'cunete-15l',
                'name' => 'Cuñete 15 Litros',
                'symbol' => '15 L',
                'description' => 'Balde industrial métrico',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 15.0,
            ],
            [
                'code' => 'galon-5l',
                'name' => 'Galón 5 Litros',
                'symbol' => '5 L',
                'description' => 'Presentación 5 litros',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 5.0,
            ],
            [
                'code' => 'bidon-5gl',
                'name' => 'Bidón 5 Galones',
                'symbol' => '5 gl',
                'description' => 'Recipiente tipo jerrican',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 18.9271,
            ],
            [
                'code' => 'tambor-50gl',
                'name' => 'Tambor 50 Galones',
                'symbol' => '50 gl',
                'description' => 'Tambor metálico/plástico industrial',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 189.27,
            ],
            [
                'code' => 'balde-2.5gl',
                'name' => 'Balde 2.5 Galones',
                'symbol' => '2.5 gal',
                'description' => 'Balde mediano',
                'to_kg_conversion' => null,
                'to_liter_conversion' => 9.4635,
            ],
            // Otros
            [
                'code' => 'u',
                'name' => 'Unidad',
                'symbol' => 'u',
                'description' => 'Unidad para conteo (envases, tapas, etc.)',
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
