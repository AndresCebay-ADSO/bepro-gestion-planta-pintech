<?php

namespace Database\Seeders;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Database\Seeder;

class FormulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $kgUnit = UnitOfMeasure::where('symbol', 'kg')->orWhere('name', 'Kilogramo')->first();
        $admin = User::first();

        // Estructura de prueba para fórmulas de productos específicos
        $formulas = [
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
                    $rmCode = 'AGUA DESTILADA';
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

            $this->command->info("Fórmula creada exitosamente para: {$productName}");
        }
    }
}
