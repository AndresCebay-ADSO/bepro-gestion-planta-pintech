<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\Product;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea una fórmula activa con sus líneas. La versión y la referencia de precio las pone el modelo: la siguiente versión
 * del producto, y de referencia solo si el producto no tenía otra (D-FOR1). Crear una versión más no cambia el precio.
 */
class CreateFormulaAction
{
    public function __construct(
        private readonly ProductionCostRecalculationService $costRecalculation,
    ) {}

    /**
     * @param  array{
     *   product_id:int,
     *   name?:string|null,
     *   notes?:string|null,
     *   details:array<int, array{raw_material_id:int, quantity:string|int|float, unit_of_measure_id:int}>
     * }  $data
     */
    public function execute(array $data, int $userId): Formula
    {
        $formula = DB::transaction(function () use ($data, $userId): Formula {
            // Con el producto bloqueado, dos fórmulas creadas a la vez no sacan la misma versión ni reclaman las dos la
            // referencia, y una desactivación simultánea del producto espera y se ve aquí (D-FOR3).
            $productActive = Product::query()->whereKey($data['product_id'])->where('is_active', true)->lockForUpdate()->first(['id']) !== null;

            if (! $productActive) {
                throw ValidationException::withMessages(['product_id' => __('El producto se desactivó mientras se creaba la fórmula.')]);
            }

            $formula = Formula::create([
                'product_id' => $data['product_id'],
                'name' => $data['name'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($data['details'] as $index => $detail) {
                FormulaDetail::create([
                    'formula_id' => $formula->id,
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                    'unit_of_measure_id' => $detail['unit_of_measure_id'],
                    'step_order' => $index + 1,
                ]);
            }

            return $formula;
        });

        if ($formula->is_price_reference) {
            $this->costRecalculation->recalculateForProduct((int) $formula->product_id);
        }

        return $formula;
    }
}
