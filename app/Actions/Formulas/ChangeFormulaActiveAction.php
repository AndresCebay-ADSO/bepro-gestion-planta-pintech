<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Models\Formula;
use App\Models\Product;
use App\Services\ProductionCostRecalculationService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Activa o desactiva una sola fórmula: varias pueden estar activas a la vez (D-FOR1). La de referencia de precio no se
 * desactiva (P9-2). Activar una en un producto sin referencia la deja de referencia (regla del modelo) y lo costea.
 */
class ChangeFormulaActiveAction
{
    public function __construct(
        private readonly ProductionCostRecalculationService $costRecalculation,
    ) {}

    /**
     * @throws DomainException si se intenta desactivar la de referencia.
     */
    public function execute(Formula $formula, bool $active): Formula
    {
        $becameReference = false;

        $locked = DB::transaction(function () use ($formula, $active, &$becameReference): Formula {
            Product::query()->whereKey($formula->product_id)->lockForUpdate()->first(['id']);
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
