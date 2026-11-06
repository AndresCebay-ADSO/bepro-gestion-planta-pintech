<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Models\RawMaterial;
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
        // En staging no hay usuarios sembrados: el primero es el creado con `users:create-admin`, y debe existir antes
        // (`formulas.created_by` es obligatorio).
        $admin = User::first();

        if ($admin === null) {
            $this->command?->warn('FormulaSeeder omitido: no hay un usuario que figure como creador. Crea el primero con: php artisan users:create-admin');

            return;
        }

        // Fórmulas ficticias de demostración: [código de materia prima, cantidad en kg]. No son las de la planta. Las
        // reales nunca se escriben en el repositorio, que es público: entran por la aplicación. Las proporciones son
        // verosímiles para cada tipo de producto, para que los costos de la demostración sean creíbles.
        $formulas = [
            // Ajustadores: solo solventes.
            'BP AJUSTADOR IA-500' => [
                ['S-2', 1.2],
                ['S-4', 1.75],
            ],
            'BP AJUSTADOR IP-350' => [
                ['S-3', 2.1],
                ['S-7', 0.62],
                ['S-13', 0.24],
            ],
            'BP AJUSTADOR AP-100' => [
                ['S-4', 1.9],
                ['S-5', 0.7],
                ['S-12', 0.4],
            ],
            'BP AJUSTADOR IE-400 FM' => [
                ['S-7', 1.3],
                ['S-10', 0.85],
                ['S-6', 0.55],
                ['S-2', 0.3],
            ],
            'BP AJUSTADOR IP-300' => [
                ['S-11', 1.15],
                ['S-9 R', 0.95],
                ['S-3', 0.6],
                ['S-14', 0.32],
            ],

            // Esmaltes: resina, pigmento y carga, solventes y aditivos.
            'BP ESMALTE POLIURETANO BLANCO' => [
                ['R-12', 1.7],
                ['S-3', 0.4],
                ['ABS-2', 0.012],
                ['P-3', 1.1],
                ['C-2', 0.35],
                ['ABS-8', 0.02],
                ['S-10', 0.3],
                ['ABS-14', 0.008],
                ['S-6', 0.18],
            ],
            'BP ESMALTE INDUSTRIAL 2 EN 1 BLANCO' => [
                ['R-3', 1.3],
                ['S-2', 0.55],
                ['ABS-5 P', 0.01],
                ['P-3', 0.85],
                ['C-2', 0.4],
                ['R-3', 0.35],
                ['S-5', 0.3],
                ['ABS-18', 0.012],
                ['ABS-9 A', 0.02],
            ],

            // Anticorrosivos base agua: agua, aditivos base agua, cargas, pigmento y resina.
            'BP ANTICORROSIVO BASE AGUA ROJO' => [
                ['AGUA', 1.05],
                ['ABA-9', 0.05],
                ['ABA-11', 0.015],
                ['C-9', 0.8],
                ['P-12', 0.3],
                ['C-4', 1.4],
                ['R-15', 1.1],
                ['ABA-12', 0.03],
                ['ABA-14', 0.012],
                ['ABA-6', 0.018],
            ],
            'BP ANTICORROSIVO BASE AGUA GRIS CLARO' => [
                ['AGUA', 1.1],
                ['ABA-1 PQ', 0.045],
                ['ABA-10 P', 0.012],
                ['C-12', 0.9],
                ['P-20 C', 0.25],
                ['C-4', 1.25],
                ['R-15', 1.05],
                ['ABA-13', 0.025],
                ['ABA-9', 0.015],
                ['P-6', 0.004],
            ],

            // Anticorrosivo y primers base solvente.
            'BP ANTICORROSIVO INDUSTRIAL NEGRO' => [
                ['R-2', 1.45],
                ['S-1', 0.7],
                ['ABS-16', 0.01],
                ['C-8', 1.3],
                ['P-5', 0.12],
                ['ABS-17', 0.006],
                ['S-1', 0.55],
                ['ABS-20', 0.015],
            ],
            'BP CATALIZADOR PRIMER ESTRUCTURAL' => [
                ['R-8 S', 1.1],
                ['S-5', 0.95],
                ['S-13', 0.6],
                ['ABS-19', 0.01],
            ],
            'BP PRIMER EPOXICO HS 2K TINTEABLE' => [
                ['R-9', 0.95],
                ['ABS-4 C', 0.015],
                ['ABS-13', 0.02],
                ['C-11', 1.6],
                ['C-13', 0.7],
                ['S-7', 0.4],
                ['S-4', 0.35],
                ['ABS-15 A', 0.012],
            ],
            'BP PRIMER EPOXICO HS 2K GRIS CLARO' => [
                ['R-9', 0.9],
                ['ABS-4 C', 0.015],
                ['C-11', 1.5],
                ['C-14', 0.65],
                ['P-20 C', 0.22],
                ['P-5', 0.006],
                ['S-7', 0.42],
                ['S-4', 0.33],
                ['ABS-15 A', 0.012],
            ],
            'BP PRIMER ESTRUCTURAL NEGRO' => [
                ['R-6 A', 0.85],
                ['ABS-2 C', 0.008],
                ['C-8', 1.9],
                ['C-5', 0.55],
                ['P-5', 0.25],
                ['S-5', 0.5],
                ['S-11', 0.3],
                ['ABS-14 C', 0.01],
            ],
        ];

        foreach ($formulas as $productName => $ingredients) {
            $product = Product::where('name', $productName)->first();

            if (! $product) {
                $this->command->warn("Producto no encontrado: {$productName}. No se pudo crear su fórmula.");

                continue;
            }

            // La siembra corre sin eventos de modelo (WithoutModelEvents en DatabaseSeeder), así que la regla de Formula que
            // deja de referencia la primera activa no actúa aquí: la v1 se marca a mano. Sin referencia no se costea.
            $formula = Formula::firstOrCreate(
                ['product_id' => $product->id, 'version' => 1],
                [
                    'is_active' => true,
                    'is_price_reference' => true,
                    'created_by' => $admin->id,
                    'notes' => 'Fórmula ficticia de demostración.',
                ]
            );

            // Una fórmula que ya tiene líneas no se toca: se edita desde la pantalla, y si ya la usa una OP ni siquiera
            // se puede editar. Solo se completa la que quedó sin líneas (una siembra cortada a mitad).
            if ($formula->details()->exists()) {
                continue;
            }

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
                    // En la unidad de la materia prima (todas las químicas se siembran en kg): una línea en otra unidad de
                    // otra base no se puede convertir, ni para costear ni para crear la OP (FOR-02).
                    'unit_of_measure_id' => $rm->unit_of_measure_id,
                    'step_order' => $step,
                ]);

                $step++;
            }

            $recalculationService->recalculateForProduct((int) $product->id);

            $this->command->info("Fórmula y costos calculados para: {$productName}");
        }
    }
}
