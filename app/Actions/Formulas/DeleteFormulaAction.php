<?php

declare(strict_types=1);

namespace App\Actions\Formulas;

use App\Actions\Formulas\Concerns\LocksFormulaProduct;
use App\Actions\Shared\DeleteUnusedRecordAction;
use App\Models\Formula;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Elimina una fórmula que nunca se usó (ninguna OP ni costo calculado; sus ingredientes se van con ella,
 * docs/POLITICA_ELIMINACION.md). Bloquea antes el producto, como toda escritura de fórmulas: una OP que se crea a la vez
 * con esta fórmula espera y la encuentra borrada, en vez de chocar con la clave foránea.
 */
class DeleteFormulaAction
{
    use LocksFormulaProduct;

    public function __construct(
        private readonly DeleteUnusedRecordAction $deleteUnused,
    ) {}

    /**
     * @return bool `false` si tiene historial (se ofrece desactivarla).
     *
     * @throws DomainException si es la de referencia de precio (P9-2).
     */
    public function execute(Formula $formula): bool
    {
        return DB::transaction(function () use ($formula): bool {
            $this->lockProduct((int) $formula->product_id);

            return $this->deleteUnused->execute($formula, function (Formula $locked): void {
                if ($locked->is_price_reference) {
                    throw new DomainException(__('Esta fórmula define el precio del producto: marca otra como referencia de precio antes de eliminarla.'));
                }
            });
        });
    }
}
