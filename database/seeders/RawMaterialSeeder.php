<?php

namespace Database\Seeders;

use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\UnitOfMeasure;
use Illuminate\Database\Seeder;

/**
 * Seeder for RawMaterial model.
 * Organizado por categorías: Químicos, Envases Metálicos, Envases Plásticos.
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

        // Get category IDs
        $catQuimicos = RawMaterialCategory::where('code', 'QUIMICOS')->first();
        $catEnvMetal = RawMaterialCategory::where('code', 'ENV-METAL')->first();
        $catEnvPlast = RawMaterialCategory::where('code', 'ENV-PLAST')->first();
        $catTapas = RawMaterialCategory::where('code', 'TAPAS')->first();

        // --- 1. QUÍMICOS Y MATERIAS PRIMAS BASE ---
        $quimicos = [
            // Pigmentos
            ['code' => 'PIG-BLA-01', 'unit' => 'kg', 'price' => 18.5000, 'name' => 'Pigmento Blanco (Dióxido de Titanio)'],
            ['code' => 'PIG-NEG-01', 'unit' => 'kg', 'price' => 22.0000, 'name' => 'Pigmento Negro (Negro de Humo)'],
            ['code' => 'PIG-AMA-01', 'unit' => 'kg', 'price' => 25.5000, 'name' => 'Pigmento Amarillo'],
            ['code' => 'PIG-AMA-CAT', 'unit' => 'kg', 'price' => 28.0000, 'name' => 'Pigmento Amarillo Caterpillar'],
            ['code' => 'PIG-AZU-01', 'unit' => 'kg', 'price' => 24.0000, 'name' => 'Pigmento Azul'],
            ['code' => 'PIG-ROJ-01', 'unit' => 'kg', 'price' => 26.5000, 'name' => 'Pigmento Rojo'],
            ['code' => 'PIG-VER-01', 'unit' => 'kg', 'price' => 23.5000, 'name' => 'Pigmento Verde'],
            ['code' => 'PIG-GRI-CLA', 'unit' => 'kg', 'price' => 19.0000, 'name' => 'Pigmento Gris Claro'],
            ['code' => 'PIG-GRI-OSC', 'unit' => 'kg', 'price' => 19.5000, 'name' => 'Pigmento Gris Oscuro'],
            ['code' => 'PIG-OCR-01', 'unit' => 'kg', 'price' => 21.0000, 'name' => 'Pigmento Ocre'],
            ['code' => 'PIG-ALU-01', 'unit' => 'kg', 'price' => 35.0000, 'name' => 'Pigmento Aluminio'],

            // Resinas
            ['code' => 'RES-ALQ-01', 'unit' => 'kg', 'price' => 12.5000, 'name' => 'Resina Alquídica'],
            ['code' => 'RES-ACR-01', 'unit' => 'kg', 'price' => 24.9000, 'name' => 'Resina Acrílica'],
            ['code' => 'RES-EPX-01', 'unit' => 'kg', 'price' => 32.0000, 'name' => 'Resina Epóxica Base'],
            ['code' => 'RES-POL-01', 'unit' => 'kg', 'price' => 28.5000, 'name' => 'Resina Poliuretano'],
            ['code' => 'RES-NOV-01', 'unit' => 'kg', 'price' => 38.0000, 'name' => 'Resina Novolac'],

            // Catalizadores
            ['code' => 'CAT-SMR-01', 'unit' => 'l', 'price' => 45.0000, 'name' => 'Catalizador SMR'],
            ['code' => 'CAT-ADX-01', 'unit' => 'l', 'price' => 52.0000, 'name' => 'Catalizador Aducto Amida'],
            ['code' => 'CAT-POL-01', 'unit' => 'l', 'price' => 48.0000, 'name' => 'Catalizador Epoxi Pol'],

            // Solventes y Ajustadores
            ['code' => 'SOL-MIN-01', 'unit' => 'l', 'price' => 8.5000, 'name' => 'Solvente Mineral'],
            ['code' => 'AJ-AP100', 'unit' => 'l', 'price' => 12.7500, 'name' => 'Ajustador AP-100'],
            ['code' => 'AJ-AP200', 'unit' => 'l', 'price' => 13.5000, 'name' => 'Ajustador AP-200'],
            ['code' => 'AJ-AP150', 'unit' => 'l', 'price' => 14.0000, 'name' => 'Ajustador AP-150'],
            ['code' => 'AJ-IP300', 'unit' => 'l', 'price' => 15.5000, 'name' => 'Ajustador IP-300'],
            ['code' => 'AJ-IP350', 'unit' => 'l', 'price' => 16.0000, 'name' => 'Ajustador IP-350'],
            ['code' => 'AJ-IE400', 'unit' => 'l', 'price' => 14.7500, 'name' => 'Ajustador IE-400'],
            ['code' => 'AJ-IA500', 'unit' => 'l', 'price' => 13.2500, 'name' => 'Ajustador IA-500'],

            // Aditivos
            ['code' => 'ADI-SEC-01', 'unit' => 'kg', 'price' => 18.0000, 'name' => 'Aditivo Secado Rápido'],
            ['code' => 'ADI-MAT-01', 'unit' => 'kg', 'price' => 15.5000, 'name' => 'Aditivo Mate'],
            ['code' => 'ADI-ANT-01', 'unit' => 'kg', 'price' => 22.0000, 'name' => 'Aditivo Anticorrosivo'],
        ];

        foreach ($quimicos as $item) {
            $unit = UnitOfMeasure::where('code', $item['unit'])->first();
            RawMaterial::updateOrCreate(
                ['code' => $item['code']],
                [
                    'category_id' => $catQuimicos?->id,
                    'unit_of_measure_id' => $unit->id,
                    'current_price' => $item['price'],
                    'previous_price' => null,
                    'minimum_stock' => 50,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]
            );
        }

        // --- 2. ENVASES METÁLICOS ---
        $envasesMetalicos = [
            ['code' => 'ENV-M-CU20', 'price' => 8500, 'name' => 'Cuñete Metálico 20L (5GL)', 'capacity' => '20L / 5GL'],
            ['code' => 'ENV-M-GL', 'price' => 3500, 'name' => 'Galón Metálico 3.785L', 'capacity' => '3.785L / 1GL'],
            ['code' => 'ENV-M-3/4', 'price' => 2800, 'name' => 'Envase Metálico 3/4 GL', 'capacity' => '2.84L'],
            ['code' => 'ENV-M-1/4-BA', 'price' => 1500, 'name' => 'Envase 1/4 Boca Ancha', 'capacity' => '0.946L'],
            ['code' => 'ENV-M-1/4-TF', 'price' => 1400, 'name' => 'Envase 1/4 Tapa Flex', 'capacity' => '0.946L'],
            ['code' => 'ENV-M-1/16', 'price' => 950, 'name' => 'Envase 1/16 Tapa Flex', 'capacity' => '0.237L'],
            ['code' => 'ENV-M-T50', 'price' => 45000, 'name' => 'Tambor Metálico 50GL', 'capacity' => '189.25L'],
            ['code' => 'ENV-M-CIN', 'price' => 12000, 'name' => 'Envase CIN 18.94L', 'capacity' => '18.94L'],
            ['code' => 'ENV-M-GL5L', 'price' => 3200, 'name' => 'Galón Metálico 5L', 'capacity' => '5L'],
        ];

        foreach ($envasesMetalicos as $pack) {
            RawMaterial::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'category_id' => $catEnvMetal?->id,
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => $pack['price'],
                    'previous_price' => null,
                    'minimum_stock' => 100,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        // --- 3. ENVASES PLÁSTICOS ---
        $envasesPlasticos = [
            ['code' => 'ENV-P-CU20', 'price' => 6500, 'name' => 'Cuñete Plástico 20L', 'capacity' => '20L'],
            ['code' => 'ENV-P-GL', 'price' => 2500, 'name' => 'Galón Plástico 3.785L', 'capacity' => '3.785L'],
            ['code' => 'ENV-P-BI5', 'price' => 7500, 'name' => 'Bidón Plástico 5GL', 'capacity' => '18.927L'],
            ['code' => 'ENV-P-T50', 'price' => 38000, 'name' => 'Tambor Plástico 50GL', 'capacity' => '189.27L'],
        ];

        foreach ($envasesPlasticos as $pack) {
            RawMaterial::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'category_id' => $catEnvPlast?->id,
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => $pack['price'],
                    'previous_price' => null,
                    'minimum_stock' => 100,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        // --- 4. TAPAS Y ACCESORIOS ---
        $tapas = [
            ['code' => 'TAP-TF-1/4', 'price' => 350, 'name' => 'Tapa Flex 1/4'],
            ['code' => 'TAP-TF-1/16', 'price' => 280, 'name' => 'Tapa Flex 1/16'],
            ['code' => 'TAP-RO-GL', 'price' => 450, 'name' => 'Tapa Rosca Galón'],
            ['code' => 'TAP-RO-CU', 'price' => 650, 'name' => 'Tapa Rosca Cuñete'],
            ['code' => 'ASA-CU-20', 'price' => 850, 'name' => 'Asa para Cuñete 20L'],
        ];

        foreach ($tapas as $tapa) {
            RawMaterial::updateOrCreate(
                ['code' => $tapa['code']],
                [
                    'category_id' => $catTapas?->id,
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => $tapa['price'],
                    'previous_price' => null,
                    'minimum_stock' => 200,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        // --- 5. Materiales adicionales para testing (químicos varios) ---
        $units = ['kg', 'l'];

        for ($i = 1; $i <= 30; $i++) {
            $unitCode = $units[array_rand($units)];
            $unitId = ($unitCode === 'kg') ? $kgUnit->id : $literUnit->id;

            $code = 'MP-'.str_pad($i + 100, 4, '0', STR_PAD_LEFT);

            RawMaterial::updateOrCreate(
                ['code' => $code],
                [
                    'category_id' => $catQuimicos?->id,
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

        $this->command->info('Created/Updated '.RawMaterial::count().' raw materials:');
        $this->command->info('  - Químicos: '.RawMaterial::where('category_id', $catQuimicos?->id)->count());
        $this->command->info('  - Envases Metálicos: '.RawMaterial::where('category_id', $catEnvMetal?->id)->count());
        $this->command->info('  - Envases Plásticos: '.RawMaterial::where('category_id', $catEnvPlast?->id)->count());
        $this->command->info('  - Tapas y Accesorios: '.RawMaterial::where('category_id', $catTapas?->id)->count());
    }
}
