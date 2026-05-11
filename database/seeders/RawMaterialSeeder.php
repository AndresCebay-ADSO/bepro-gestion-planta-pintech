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
        $gramUnit = UnitOfMeasure::where('code', 'gr')->first();
        $kgUnit = UnitOfMeasure::where('code', 'kg')->first();
        $literUnit = UnitOfMeasure::where('code', 'l')->first();
        $unitUnit = UnitOfMeasure::where('code', 'u')->first();

        // Get category IDs
        $catQuimicos = RawMaterialCategory::where('code', 'QUIMICOS')->first();
        $catEnvMetal = RawMaterialCategory::where('code', 'ENV-METAL')->first();
        $catEnvPlast = RawMaterialCategory::where('code', 'ENV-PLAST')->first();

        // --- 1. QUÍMICOS Y MATERIAS PRIMAS BASE ---
        $quimicos = [

            // Aditivo
            ['code' => 'A-1', 'unit' => 'kg'],
            ['code' => 'A-2', 'unit' => 'kg'],
            ['code' => 'A-3 A', 'unit' => 'kg'],
            ['code' => 'A-3 C', 'unit' => 'kg'],
            ['code' => 'A-4', 'unit' => 'kg'],
            ['code' => 'A-5', 'unit' => 'kg'],
            ['code' => 'A-6', 'unit' => 'kg'],
            ['code' => 'A-7', 'unit' => 'kg'],
            ['code' => 'A-8', 'unit' => 'kg'],
            ['code' => 'A-9', 'unit' => 'kg'],

            // Aditivo Base Agua
            ['code' => 'ABA-1', 'unit' => 'kg'],
            ['code' => 'ABA-1 PQ', 'unit' => 'kg'],
            ['code' => 'ABA-2 A', 'unit' => 'kg'],
            ['code' => 'ABA-2 P', 'unit' => 'kg'],
            ['code' => 'ABA-3 P', 'unit' => 'kg'],
            ['code' => 'ABA-3 QC', 'unit' => 'kg'],
            ['code' => 'ABA-4 S', 'unit' => 'kg'],
            ['code' => 'ABA-4', 'unit' => 'kg'],
            ['code' => 'ABA-5', 'unit' => 'kg'],
            ['code' => 'ABA-6', 'unit' => 'kg'],
            ['code' => 'ABA-7', 'unit' => 'l'],
            ['code' => 'ABA-7 B', 'unit' => 'l'],
            ['code' => 'ABA-8', 'unit' => 'l'],
            ['code' => 'ABA-9', 'unit' => 'l'],
            ['code' => 'ABA-10', 'unit' => 'l'],
            ['code' => 'ABA-10 P', 'unit' => 'l'],
            ['code' => 'ABA-11', 'unit' => 'l'],
            ['code' => 'ABA-12', 'unit' => 'l'],
            ['code' => 'ABA-13', 'unit' => 'l'],
            ['code' => 'ABA-14', 'unit' => 'l'],

            // Aditivo Base Solvente
            ['code' => 'ABS-1', 'unit' => 'kg'],
            ['code' => 'ABS-1 PU', 'unit' => 'kg'],
            ['code' => 'ABS-2', 'unit' => 'kg'],
            ['code' => 'ABS-2 C', 'unit' => 'kg'],
            ['code' => 'ABS-2 CC', 'unit' => 'kg'],
            ['code' => 'ABS-3', 'unit' => 'kg'],
            ['code' => 'ABS-3 PU', 'unit' => 'kg'],
            ['code' => 'ABS-3 POL', 'unit' => 'kg'],
            ['code' => 'ABS-4', 'unit' => 'kg'],
            ['code' => 'ABS-4 C', 'unit' => 'kg'],
            ['code' => 'ABS-4 P', 'unit' => 'kg'],
            ['code' => 'ABS-4 R', 'unit' => 'kg'],
            ['code' => 'ABS-4 E', 'unit' => 'kg'],
            ['code' => 'ABS-4 POL', 'unit' => 'l'],
            ['code' => 'ABS-5', 'unit' => 'l'],
            ['code' => 'ABS-5 P', 'unit' => 'l'],
            ['code' => 'ABS-6 ', 'unit' => 'l'],
            ['code' => 'ABS-7', 'unit' => 'l'],
            ['code' => 'ABS-7 ALQ', 'unit' => 'l'],
            ['code' => 'ABS-8', 'unit' => 'l'],
            ['code' => 'ABS-8 C', 'unit' => 'l'],
            ['code' => 'ABS-9', 'unit' => 'l'],
            ['code' => 'ABS-9 A', 'unit' => 'l'],
            ['code' => 'ABS-9 P', 'unit' => 'kg'],
            ['code' => 'ABS-9 QC', 'unit' => 'kg'],
            ['code' => 'ABS-10', 'unit' => 'kg'],
            ['code' => 'ABS-11 QC', 'unit' => 'kg'],
            ['code' => 'ABS-11 P', 'unit' => 'kg'],
            ['code' => 'ABS-12 A', 'unit' => 'kg'],
            ['code' => 'ABS-12 QC', 'unit' => 'kg'],
            ['code' => 'ABS-13', 'unit' => 'kg'],
            ['code' => 'ABS-13 QC', 'unit' => 'kg'],
            ['code' => 'ABS-14', 'unit' => 'kg'],
            ['code' => 'ABS-14 C', 'unit' => 'l'],
            ['code' => 'ABS-15', 'unit' => 'l'],
            ['code' => 'ABS-16', 'unit' => 'l'],
            ['code' => 'ABS-17', 'unit' => 'l'],
            ['code' => 'ABS-18', 'unit' => 'l'],
            ['code' => 'ABS-19', 'unit' => 'l'],
            ['code' => 'ABS-20', 'unit' => 'l'],

            // Carga
            ['code' => 'C-1', 'unit' => 'kg'],
            ['code' => 'C-2', 'unit' => 'kg'],
            ['code' => 'C-3', 'unit' => 'kg'],
            ['code' => 'C-3 ROCSA P', 'unit' => 'kg'],
            ['code' => 'C-3 POL', 'unit' => 'kg'],
            ['code' => 'C-3 POL P', 'unit' => 'kg'],
            ['code' => 'C-4', 'unit' => 'kg'],
            ['code' => 'C-5', 'unit' => 'kg'],
            ['code' => 'C-5 QC', 'unit' => 'kg'],
            ['code' => 'C-6', 'unit' => 'kg'],
            ['code' => 'C-6 1 HD', 'unit' => 'kg'],
            ['code' => 'C-6 1 NT', 'unit' => 'kg'],
            ['code' => 'C-7', 'unit' => 'kg'],
            ['code' => 'C-7 QM', 'unit' => 'kg'],
            ['code' => 'C-7 M', 'unit' => 'kg'],
            ['code' => 'C-8', 'unit' => 'kg'],
            ['code' => 'C-9', 'unit' => 'kg'],
            ['code' => 'C-10', 'unit' => 'kg'],
            ['code' => 'C-11', 'unit' => 'kg'],
            ['code' => 'C-11 M', 'unit' => 'kg'],
            ['code' => 'C-12', 'unit' => 'kg'],
            ['code' => 'C-13', 'unit' => 'kg'],
            ['code' => 'C-14', 'unit' => 'kg'],

            // Serie P
            ['code' => 'P-1', 'unit' => 'kg'],
            ['code' => 'P-2', 'unit' => 'kg'],
            ['code' => 'P-3', 'unit' => 'kg'],
            ['code' => 'P-4', 'unit' => 'kg'],
            ['code' => 'P-5', 'unit' => 'kg'],
            ['code' => 'P-6', 'unit' => 'kg'],
            ['code' => 'P-7', 'unit' => 'kg'],
            ['code' => 'P-8', 'unit' => 'kg'],
            ['code' => 'P-9', 'unit' => 'kg'],
            ['code' => 'P-10', 'unit' => 'kg'],
            ['code' => 'P-10 P', 'unit' => 'kg'],
            ['code' => 'P-11', 'unit' => 'kg'],
            ['code' => 'P-12', 'unit' => 'kg'],
            ['code' => 'P-13', 'unit' => 'kg'],
            ['code' => 'P-14', 'unit' => 'kg'],
            ['code' => 'P-15', 'unit' => 'kg'],
            ['code' => 'P-16', 'unit' => 'kg'],
            ['code' => 'P-17', 'unit' => 'kg'],
            ['code' => 'P-18', 'unit' => 'kg'],
            ['code' => 'P-19', 'unit' => 'kg'],
            ['code' => 'P-20', 'unit' => 'kg'],
            ['code' => 'P-20B', 'unit' => 'kg'],
            ['code' => 'P-20 C', 'unit' => 'kg'],
            ['code' => 'P-21', 'unit' => 'kg'],
            ['code' => 'P-21 QC', 'unit' => 'kg'],
            ['code' => 'P-22', 'unit' => 'kg'],
            ['code' => 'P-23', 'unit' => 'kg'],
            ['code' => 'P-24', 'unit' => 'kg'],
            ['code' => 'P-25', 'unit' => 'kg'],
            ['code' => 'P-26', 'unit' => 'kg'],
            ['code' => 'P-27', 'unit' => 'kg'],
            ['code' => 'P-28', 'unit' => 'kg'],
            ['code' => 'P-29', 'unit' => 'kg'],
            ['code' => 'P-30', 'unit' => 'kg'],
            ['code' => 'P-31', 'unit' => 'kg'],
            ['code' => 'P-614', 'unit' => 'kg'],
            ['code' => 'P-637', 'unit' => 'kg'],
            ['code' => 'P-628', 'unit' => 'kg'],
            ['code' => 'P-608', 'unit' => 'kg'],

            // Serie R
            ['code' => 'R-1 A', 'unit' => 'kg'],
            ['code' => 'R-2', 'unit' => 'kg'],
            ['code' => 'R-3', 'unit' => 'kg'],
            ['code' => 'R-4', 'unit' => 'kg'],
            ['code' => 'R-4 T', 'unit' => 'kg'],
            ['code' => 'R-4 A', 'unit' => 'kg'],
            ['code' => 'R-4 P', 'unit' => 'kg'],
            ['code' => 'R-5 S', 'unit' => 'kg'],
            ['code' => 'R-5 A', 'unit' => 'kg'],
            ['code' => 'R-5 C', 'unit' => 'kg'],
            ['code' => 'R-5', 'unit' => 'kg'],
            ['code' => 'R-6 S', 'unit' => 'kg'],
            ['code' => 'R-6 S 115', 'unit' => 'kg'],
            ['code' => 'R-6 A', 'unit' => 'kg'],
            ['code' => 'R-6 C', 'unit' => 'kg'],
            ['code' => 'R-7 A', 'unit' => 'kg'],
            ['code' => 'R-8 S', 'unit' => 'kg'],
            ['code' => 'R-8 P', 'unit' => 'kg'],
            ['code' => 'R-9', 'unit' => 'kg'],
            ['code' => 'R-10', 'unit' => 'kg'],
            ['code' => 'R-11', 'unit' => 'kg'],
            ['code' => 'R-12', 'unit' => 'kg'],
            ['code' => 'R-14', 'unit' => 'kg'],
            ['code' => 'R-13', 'unit' => 'kg'],
            ['code' => 'R-15', 'unit' => 'kg'],
            ['code' => 'R-16', 'unit' => 'kg'],
            ['code' => 'R-17', 'unit' => 'kg'],
            ['code' => 'R-18', 'unit' => 'kg'],
            ['code' => 'R-20', 'unit' => 'kg'],
            ['code' => 'R-21', 'unit' => 'kg'],
            ['code' => 'R-22', 'unit' => 'kg'],
            ['code' => 'R-23', 'unit' => 'kg'],
            ['code' => 'R-24', 'unit' => 'kg'],
            ['code' => 'R-25', 'unit' => 'kg'],

            // Serie S
            ['code' => 'S-1', 'unit' => 'kg'],
            ['code' => 'S-2', 'unit' => 'kg'],
            ['code' => 'S-3', 'unit' => 'kg'],
            ['code' => 'S-4', 'unit' => 'kg'],
            ['code' => 'S-4 R', 'unit' => 'kg'],
            ['code' => 'S-5', 'unit' => 'kg'],
            ['code' => 'S-5 P', 'unit' => 'kg'],
            ['code' => 'S-6', 'unit' => 'kg'],
            ['code' => 'S-7 POL', 'unit' => 'kg'],
            ['code' => 'S-7', 'unit' => 'kg'],
            ['code' => 'S-7 DBBW', 'unit' => 'kg'],
            ['code' => 'S-7 R', 'unit' => 'kg'],
            ['code' => 'S-8 B', 'unit' => 'kg'],
            ['code' => 'S-9', 'unit' => 'kg'],
            ['code' => 'S-10', 'unit' => 'kg'],
            ['code' => 'S-11', 'unit' => 'kg'],
            ['code' => 'S-11 R', 'unit' => 'kg'],
            ['code' => 'S-11 S', 'unit' => 'kg'],
            ['code' => 'S-12', 'unit' => 'kg'],
            ['code' => 'S-13', 'unit' => 'kg'],
            ['code' => 'S-14', 'unit' => 'kg'],

            // Serie O y otros
            ['code' => 'O-1', 'unit' => 'kg'],
            ['code' => 'O-2', 'unit' => 'kg'],
            ['code' => 'O-3', 'unit' => 'kg'],
            ['code' => 'AGUA DESTILADA', 'unit' => 'l'],

            // Serie AP y Químicos Finales
            ['code' => 'AP-423', 'unit' => 'kg'],
            ['code' => 'AP-425', 'unit' => 'kg'],
            ['code' => 'AP-404', 'unit' => 'kg'],
            ['code' => 'AP-405', 'unit' => 'kg'],
            ['code' => 'AP-402', 'unit' => 'kg'],
            ['code' => 'AP-415', 'unit' => 'kg'],
            ['code' => 'AP-435', 'unit' => 'kg'],
            ['code' => 'AP-DORADA', 'unit' => 'kg'],
            ['code' => 'AP-419', 'unit' => 'kg'],
            ['code' => 'AP-421', 'unit' => 'kg'],
            ['code' => 'BENZ. S', 'unit' => 'kg'],
            ['code' => 'BS', 'unit' => 'kg'],
            ['code' => 'ETILE', 'unit' => 'kg'],
            ['code' => 'FOSF. S', 'unit' => 'kg'],
            ['code' => 'TEA', 'unit' => 'kg'],
        ];

        foreach ($quimicos as $item) {
            $unit = UnitOfMeasure::where('code', $item['unit'])->first();
            RawMaterial::updateOrCreate(
                ['code' => $item['code']],
                [
                    'category_id' => $catQuimicos?->id,
                    'unit_of_measure_id' => $unit->id,
                    'current_price' => null,
                    'previous_price' => null,
                    'minimum_stock' => 50,
                    'alert_days_before_expiry' => 30,
                    'is_active' => true,
                ]
            );
        }

        // --- 2. ENVASES METÁLICOS ---
        $envasesMetalicos = [
            ['code' => 'ENV-M-CU20'],
            ['code' => 'ENV-M-GL'],
            ['code' => 'ENV-M-3/4'],
            ['code' => 'ENV-M-1/4-BA'],
            ['code' => 'ENV-M-1/4-TF'],
            ['code' => 'ENV-M-1/16'],
            ['code' => 'ENV-M-T50'],
            ['code' => 'ENV-M-CIN'],
            ['code' => 'ENV-M-GL5L'],
        ];

        foreach ($envasesMetalicos as $pack) {
            RawMaterial::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'category_id' => $catEnvMetal?->id,
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => null,
                    'previous_price' => null,
                    'minimum_stock' => 100,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        // --- 3. ENVASES PLÁSTICOS ---
        $envasesPlasticos = [
            ['code' => 'ENV-P-CU20'],
            ['code' => 'ENV-P-GL'],
            ['code' => 'ENV-P-BI5'],
            ['code' => 'ENV-P-T50'],
        ];

        foreach ($envasesPlasticos as $pack) {
            RawMaterial::updateOrCreate(
                ['code' => $pack['code']],
                [
                    'category_id' => $catEnvPlast?->id,
                    'unit_of_measure_id' => $unitUnit->id,
                    'current_price' => null,
                    'previous_price' => null,
                    'minimum_stock' => 100,
                    'alert_days_before_expiry' => 0,
                    'is_active' => true,
                ]
            );
        }

        $this->command->info('Created/Updated '.RawMaterial::count().' raw materials:');
        $this->command->info('  - Químicos: '.RawMaterial::where('category_id', $catQuimicos?->id)->count());
        $this->command->info('  - Envases Metálicos: '.RawMaterial::where('category_id', $catEnvMetal?->id)->count());
        $this->command->info('  - Envases Plásticos: '.RawMaterial::where('category_id', $catEnvPlast?->id)->count());
    }
}
