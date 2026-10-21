<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Actions\Formulas\Concerns\LocksFormulaProduct;
use App\Models\Formula;
use App\Services\ProductionCostRecalculationService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva una sola fórmula: varias pueden estar activas a la vez (D-FOR1). La de referencia de precio no se
 * desactiva (P9-2), y con el producto inactivo no se activa (P9-13). Activar una en un producto sin referencia la deja de
 * referencia (regla del modelo) y lo costea.
 */
class ChangeFormulaActiveAction
{
    use LocksFormulaProduct;

    public function __construct(
        private readonly ProductionCostRecalculationService $costRecalculation,
    ) {}

    /**
     * @throws DomainException si se intenta desactivar la de referencia, o activar una de un producto inactivo.
     */
    public function execute(Formula $formula, bool $active): Formula
    {
        $becameReference = false;

        $locked = DB::transaction(function () use ($formula, $active, &$becameReference): Formula {
            // Desactivar se permite siempre; activar, solo con el producto activo (P9-13).
            if ($active) {
                $this->lockActiveProduct((int) $formula->product_id);
            } else {
                $this->lockProduct((int) $formula->product_id);
            }
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->id);

            if (! $active && $locked->is_price_reference) {
                throw new DomainException(__('Esta fórmula define el precio del producto: marca otra como referencia de precio antes de desactivarla.'));
            }

            $wasReference = $locked->is_price_reference;
            $locked->update(['is_active' => $active]);
            $becameReference = ! $wasReference && $locked->is_price_reference;

            return $locked;
        });

        if ($becameReference) {
            $this->costRecalculation->recalculateForProduct((int) $locked->product_id);
        }

        return $locked;
    }
}
