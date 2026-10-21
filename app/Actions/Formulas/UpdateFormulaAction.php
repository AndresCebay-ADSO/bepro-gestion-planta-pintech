<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Actions\Formulas\Concerns\LocksFormulaProduct;
use App\Models\Formula;
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
            $this->lockProduct((int) $formula->product_id);
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->id);

            if (($blocker = $this->blocker($locked)) !== null) {
                throw new DomainException($blocker);
            }

            $locked->update([
                'name' => $data['name'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
            $locked->replaceDetails($data['details']);

            return $locked->is_price_reference;
        });

        if ($isReference) {
            $this->costRecalculation->recalculateForProduct((int) $formula->product_id);
        }
    }

    /**
     * Por qué no se puede editar la fórmula, o null. La pantalla de edición lo usa para avisar antes, y execute() lo
     * vuelve a revisar con el producto bloqueado: una sola fuente para las reglas y sus mensajes.
     */
    public function blocker(Formula $formula): ?string
    {
        if ($formula->productionOrders()->exists()) {
            return __('Esta fórmula ya fue usada en órdenes de producción y no se puede editar.');
        }

        if (! $formula->product()->value('is_active')) {
            return $this->inactiveProductMessage();
        }

        return null;
    }
}
