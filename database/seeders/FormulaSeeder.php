<?php

namespace Database\Seeders;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(ProductionCostRecalculationService $recalculationService): void
    {
        $kgUnit = UnitOfMeasure::where('symbol', 'kg')->orWhere('name', 'Kilogramo')->first();
        $admin = User::first();

        // Estructura de prueba para fórmulas de productos específicos
        $formulas = [

            // Ajustadores
            'BP ESMALTE POLIURETANO BLANCO' => [
                ['R-7 A', 1.45],
                ['ABS-1 PU', 0.03],
                ['ABA-3 P', 0.01],
                ['C-3', 0.45],
                ['C-3 POL P', 0.45],
                ['S-11 S', 0.095],
                ['S-10', 0.0225],
                ['S-8 B', 0.05],
                ['C-6', 0.404],
                ['R-7 A', 1.45],
                ['S-10', 0.075],
                ['S-4', 0.1975],
                ['S-11 S', 0.095],
                ['S-6', 0.0125],
                ['ABS-4', 0.015],
                ['ABS-5', 0.015],
                ['ABS-6', 0.0002],
            ],
            'BP ANTICORROSIVO BASE AGUA ROJO' => [
                ['AGUA', 1.2],
                ['ABA-1', 0.06],
                ['ABA-2 A', 0.01],
                ['ABS-7', 0.06],
                ['C-10', 0.35],
                ['R-10', 0.55],
                ['C-7', 1.8],
                ['ABA-3 QC', 0.0238],
                ['ABA-4', 0.0081],
                ['ABA-7', 0.02],
                ['R-10', 0.55],
                ['ABA-5', 0.04],
                ['ABA-6', 0.02],
                ['ABA-8', 0.004],
                ['ABA-10', 0.02],
                ['ABA-13', 0.06],
            ],
            'BP ESMALTE INDUSTRIAL 2 EN 1 BLANCO' => [
                ['R - 4', 0.925],
                ['S - 3', 0.425],
                ['ABS - 1 PU', 0.007],
                ['ABS - 3', 0.007],
                ['ABS - 7', 0.04],
                ['C - 3', 0.6],
                ['C - 6', 0.28],
                ['R - 4', 0.925],
                ['R - 3', 0.2],
                ['S - 3', 0.425],
                ['ABS - 9', 0.006],
                ['ABS - 13', 0.032],
            ],
            'BP AJUSTADOR IE-400 FM' => [
                ['S-7', 1],
                ['S-11', 0.17],
                ['S-4', 1.68],
                ['S-5', 0.504],
            ],
            'BP AJUSTADOR IP-350' => [
                ['S-4', 3.126],
                ['S-10', 0.105],
                ['S-6', 0.105],
            ],
            'BP AJUSTADOR AP-100' => [
                ['S-4', 2.68],
                ['S-10', 0.34],
                ['S-6', 0.34],
            ],
            'BP PRIMER EPOXICO HS 2K TINTEABLE' => [
                ['R-5 C', 0.6],
                ['ABS-2', 0.002],
                ['ABS-3', 0.01],
                ['ABS-7', 0.03],
                ['ABS-15', 0.0212],
                ['C-6', 2],
                ['S-7', 0.453],
                ['R-5 C', 0.6],
                ['S-4', 0.31],
                ['S-6', 0.038],
                ['ABS-5', 0.011],
                ['ABS-4 R', 0.008],
            ],
        ];

        foreach ($formulas as $productName => $ingredients) {
            $product = Product::where('name', $productName)->first();

            if (! $product) {
                $this->command->warn("Producto no encontrado: {$productName}. No se pudo crear su fórmula.");

                continue;
            }

            // Crear o actualizar la cabecera de la fórmula
            $formula = Formula::updateOrCreate(
                ['product_id' => $product->id, 'version' => 1],
                [
                    'is_active' => true,
                    'created_by' => $admin?->id,
                    'notes' => 'Fórmula base importada para pruebas y desarrollo.',
                ]
            );

            // Borrar detalles anteriores si es que se está re-ejecutando
            $formula->details()->delete();

            $step = 1;
            foreach ($ingredients as $ingredient) {
                $rmCode = $ingredient[0];
                $quantity = $ingredient[1];

                if (strtoupper($rmCode) === 'AGUA') {
                    $rmCode = 'AGUA DE SERVICIO';
                }

                // Buscar la materia prima por código exacto o similar
                $rm = RawMaterial::where('code', $rmCode)->first();

                if (! $rm) {
                    // Intento 2: ignorar espacios en blanco
                    $normalizedCode = preg_replace('/\s+/', '', $rmCode);
                    $rm = RawMaterial::whereRaw("REPLACE(code, ' ', '') = ?", [$normalizedCode])->first();
                }

                if (! $rm) {
                    $this->command->error("Materia prima no encontrada: {$rmCode} para el producto {$productName}");

                    continue;
                }

                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'raw_material_id' => $rm->id,
                    'quantity' => $quantity,
                    'unit_of_measure_id' => $kgUnit?->id ?? $rm->unit_of_measure_id,
                    'step_order' => $step,
                ]);

                $step++;
            }

            $recalculationService->recalculateForProduct((int) $product->id);

            $this->command->info("Fórmula y costos calculados para: {$productName}");
        }
    }
}
