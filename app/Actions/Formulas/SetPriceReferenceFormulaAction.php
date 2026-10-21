<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Models\Formula;
use App\Models\Product;
use App\Services\ProductionCostRecalculationService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Elige con qué fórmula se costea el producto (D-FOR1, «Usar para el precio»). Las demás siguen activas para producir.
 */
class SetPriceReferenceFormulaAction
{
    public function __construct(
        private readonly ProductionCostRecalculationService $costRecalculation,
    ) {}

    /**
     * @return bool `false` si ya era la de referencia (no cambia nada).
     *
     * @throws DomainException si la fórmula está inactiva.
     */
    public function execute(Formula $formula): bool
    {
        $changed = DB::transaction(function () use ($formula): bool {
            Product::query()->whereKey($formula->product_id)->lockForUpdate()->first(['id']);
            $locked = Formula::query()->lockForUpdate()->findOrFail($formula->id);

            if ($locked->is_price_reference) {
                return false;
            }

            if (! $locked->is_active) {
                throw new DomainException(__('Activa la fórmula antes de usarla para el precio.'));
            }

            // Primero se quita la anterior: el índice único parcial no admite dos referencias ni por un instante. Una por
            // una y con el modelo, para que quede en el registro de auditoría.
            Formula::query()
                ->where('product_id', $locked->product_id)
                ->priceReference()
                ->get()
                ->each(fn (Formula $previous) => $previous->update(['is_price_reference' => false]));

            $locked->update(['is_price_reference' => true]);

            return true;
        });

        // Recálculo normal, con el umbral de precio, como cualquier cambio de costo (P9-4).
        if ($changed) {
            $this->costRecalculation->recalculateForProduct((int) $formula->product_id);
        }

        return $changed;
    }
}
