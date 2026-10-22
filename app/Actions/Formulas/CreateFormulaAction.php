<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Actions\Formulas\Concerns\LocksFormulaProduct;
use App\Models\Formula;
use App\Services\ProductionCostRecalculationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Crea una fórmula activa con sus líneas. La versión y la referencia de precio las pone el modelo: la siguiente versión
 * del producto, y de referencia solo si el producto no tenía otra (D-FOR1). Crear una versión más no cambia el precio.
 */
class CreateFormulaAction
{
    use LocksFormulaProduct;

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
     *
     * @throws ValidationException si el producto se desactivó o la unidad de una línea dejó de convertirse.
     */
    public function execute(array $data, int $userId): Formula
    {
        $formula = DB::transaction(function () use ($data, $userId): Formula {
            // Con el producto bloqueado, dos fórmulas creadas a la vez no sacan la misma versión ni reclaman las dos la
            // referencia, y una desactivación simultánea del producto espera y se ve aquí (D-FOR3).
            if (! $this->lockProduct((int) $data['product_id'])) {
                throw ValidationException::withMessages(['product_id' => __('El producto se desactivó mientras se creaba la fórmula.')]);
            }

            $this->lockLineMaterialsAndCheckUnits($data['details']);

            $formula = Formula::create([
                'product_id' => $data['product_id'],
                'name' => $data['name'] ?? null,
                'is_active' => true,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            $formula->replaceDetails($data['details']);

            return $formula;
        });

        if ($formula->is_price_reference) {
            // El cambio ya quedó guardado: si el producto no se puede costear, queda en el log y no se reporta como fallo.
            $this->costRecalculation->recalculateForProductOrLog((int) $formula->product_id, ['formula_id' => (int) $formula->id]);
        }

        return $formula;
    }
}
