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
            ['code' => 'A-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-3 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-3 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-5', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-6', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-7', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-8', 'unit' => 'kg', 'price' => 1],
            ['code' => 'A-9', 'unit' => 'kg', 'price' => 1],

            // Aditivo Base Agua
            ['code' => 'ABA-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-1 PQ', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-2 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-2 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-3 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-3 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-4 S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-5', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-6', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABA-7', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-7 B', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-8', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-9', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-10', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-10 P', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-11', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-12', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-13', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABA-14', 'unit' => 'l', 'price' => 1],

            // Aditivo Base Solvente
            ['code' => 'ABS-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-1 PU', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-2 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-2 CC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-3 PU', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-3 POL', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4 R', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4 E', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-4 POL', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-5', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-5 P', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-6 ', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-7', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-7 ALQ', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-8', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-8 C', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-9', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-9 A', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-9 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-9 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-10', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-11 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-11 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-12 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-12 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-13', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-13 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-14', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ABS-14 C', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-15', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-16', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-17', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-18', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-19', 'unit' => 'l', 'price' => 1],
            ['code' => 'ABS-20', 'unit' => 'l', 'price' => 1],

            // Carga
            ['code' => 'C-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-3 ROCSA P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-3 POL', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-3 POL P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-5', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-5 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-6', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-6 1 HD', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-6 1 NT', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-7', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-7 QM', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-7 M', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-8', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-9', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-10', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-11', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-11 M', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-12', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-13', 'unit' => 'kg', 'price' => 1],
            ['code' => 'C-14', 'unit' => 'kg', 'price' => 1],

            // Serie P
            ['code' => 'P-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-5', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-6', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-7', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-8', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-9', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-10', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-10 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-11', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-12', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-13', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-14', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-15', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-16', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-17', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-18', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-19', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-20', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-20B', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-20 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-21', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-21 QC', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-22', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-23', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-24', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-25', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-26', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-27', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-28', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-29', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-30', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-31', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-614', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-637', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-628', 'unit' => 'kg', 'price' => 1],
            ['code' => 'P-608', 'unit' => 'kg', 'price' => 1],

            // Serie R
            ['code' => 'R-1 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-4 T', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-4 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-4 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-5 S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-5 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-5 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-5 R', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-6 S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-6 S 115', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-6 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-6 C', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-7 A', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-8 S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-8 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-9', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-10', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-11', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-12', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-14', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-13', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-15', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-16', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-17', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-18', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-20', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-21', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-22', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-23', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-24', 'unit' => 'kg', 'price' => 1],
            ['code' => 'R-25', 'unit' => 'kg', 'price' => 1],

            // Serie S
            ['code' => 'S-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-4', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-4 R', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-5', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-5 P', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-6', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-7 POL', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-7 CONCQUIMICA', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-7 DBBW', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-7 R', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-8 B', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-9', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-10', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-11 R', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-11 S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-12', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-13', 'unit' => 'kg', 'price' => 1],
            ['code' => 'S-14', 'unit' => 'kg', 'price' => 1],

            // Serie O y otros
            ['code' => 'O-1', 'unit' => 'kg', 'price' => 1],
            ['code' => 'O-2', 'unit' => 'kg', 'price' => 1],
            ['code' => 'O-3', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AGUA DESTILADA', 'unit' => 'l', 'price' => 1],

            // Serie AP y Químicos Finales
            ['code' => 'AP-423', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-425', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-404', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-405', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-402', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-415', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-435', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-DORADA', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-419', 'unit' => 'kg', 'price' => 1],
            ['code' => 'AP-421', 'unit' => 'kg', 'price' => 1],
            ['code' => 'BENZ. S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'BS', 'unit' => 'kg', 'price' => 1],
            ['code' => 'ETILE', 'unit' => 'kg', 'price' => 1],
            ['code' => 'FOSF. S', 'unit' => 'kg', 'price' => 1],
            ['code' => 'TEA', 'unit' => 'kg', 'price' => 1],
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
            ['code' => 'ENV-M-CU20', 'price' => 8500],
            ['code' => 'ENV-M-GL', 'price' => 3500],
            ['code' => 'ENV-M-3/4', 'price' => 2800],
            ['code' => 'ENV-M-1/4-BA', 'price' => 1500],
            ['code' => 'ENV-M-1/4-TF', 'price' => 1400],
            ['code' => 'ENV-M-1/16', 'price' => 950],
            ['code' => 'ENV-M-T50', 'price' => 45000],
            ['code' => 'ENV-M-CIN', 'price' => 12000],
            ['code' => 'ENV-M-GL5L', 'price' => 3200],
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
            ['code' => 'ENV-P-CU20', 'price' => 6500],
            ['code' => 'ENV-P-GL', 'price' => 2500],
            ['code' => 'ENV-P-BI5', 'price' => 7500],
            ['code' => 'ENV-P-T50', 'price' => 38000],
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

        $this->command->info('Created/Updated ' . RawMaterial::count() . ' raw materials:');
        $this->command->info('  - Químicos: ' . RawMaterial::where('category_id', $catQuimicos?->id)->count());
        $this->command->info('  - Envases Metálicos: ' . RawMaterial::where('category_id', $catEnvMetal?->id)->count());
        $this->command->info('  - Envases Plásticos: ' . RawMaterial::where('category_id', $catEnvPlast?->id)->count());
    }
}
