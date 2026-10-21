<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Actions\Formulas\Concerns\LocksFormulaProduct;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Services\ProductionCostRecalculationService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el nombre, las notas y las líneas de una fórmula que ninguna OP usó. Activarla o desactivarla va por
 * ChangeFormulaActiveAction (P9-3). Si es la de referencia, el producto se vuelve a costear.
 */
class UpdateFormulaAction
{
    use LocksFormulaProduct;

    public function __construct(
        private readonly ProductionCostRecalculationService $costRecalculation,
    ) {}

    /**
     * @param  array{
     *   name?:string|null,
     *   notes?:string|null,
     *   details:array<int, array{raw_material_id:int, quantity:string|int|float, unit_of_measure_id:int}>
     * }  $data
     *
     * @throws DomainException si una OP ya la usó o su producto está inactivo.
     */
    public function execute(Formula $formula, array $data): void
    {
        $isReference = DB::transaction(function () use ($formula, $data): bool {
            // Con el producto bloqueado, todo se decide con lo ya guardado: una OP que se crea a la vez (bloqueo
            // compartido del producto) o un cambio de referencia esperan, y no se edita una fórmula recién usada ni se
            // costea con las líneas viejas.
            $this->lockActiveProduct((int) $formula->product_id);
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->id);

            if ($locked->productionOrders()->exists()) {
                throw new DomainException(__('Esta fórmula ya fue usada en órdenes de producción y no se puede editar.'));
            }

            $locked->update([
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);

            $locked->details()->delete();

            foreach ($data['details'] as $index => $detail) {
                FormulaDetail::create([
                    'formula_id' => $locked->id,
                    'raw_material_id' => $detail['raw_material_id'],
                    'quantity' => $detail['quantity'],
                    'unit_of_measure_id' => $detail['unit_of_measure_id'],
                    'step_order' => $index + 1,
                ]);
            }

            return $locked->is_price_reference;
        });

        if ($isReference) {
            $this->costRecalculation->recalculateForProduct((int) $formula->product_id);
        }
    }
}
