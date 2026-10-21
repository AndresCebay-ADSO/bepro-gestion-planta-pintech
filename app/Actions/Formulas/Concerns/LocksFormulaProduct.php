<?php

declare(strict_types=1);

namespace App\Actions\Formulas\Concerns;

use App\Models\Product;
use DomainException;

/**
 * Toda escritura de fórmulas bloquea primero la fila del producto, dentro de la transacción de quien llama. Así se ponen
 * en fila entre ellas (versión, referencia, líneas) y con lo que lee la fórmula de un producto: crear una OP toma un
 * bloqueo compartido sobre el mismo producto. Orden de bloqueo: producto y después fórmula, siempre.
 */
trait LocksFormulaProduct
{
    /**
     * @return bool si el producto sigue activo.
     */
    private function lockProduct(int $productId): bool
    {
        return (bool) Product::query()->whereKey($productId)->lockForUpdate()->value('is_active');
    }

    /**
     * Con el producto inactivo, sus fórmulas solo se desactivan o se eliminan: no se crean (D-FOR3), no se editan, no se
     * activan ni cambian de referencia (P9-13). Si el producto vuelve, se reactiva primero.
     *
     * @throws DomainException
     */
    private function lockActiveProduct(int $productId): void
    {
        if (! $this->lockProduct($productId)) {
            throw new DomainException(__('El producto está inactivo: sus fórmulas solo se pueden desactivar o eliminar. Reactiva el producto para cambiarlas.'));
        }
    }
}
