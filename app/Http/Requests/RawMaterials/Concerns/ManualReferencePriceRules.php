<?php

declare(strict_types=1);

namespace App\Http\Requests\RawMaterials\Concerns;

use App\Enums\Permission;
use Illuminate\Validation\Validator;

/**
 * El precio de referencia de una materia prima sale de sus compras. Solo la que no controla inventario (agua, etiquetas)
 * no tiene compras, así que su precio se escribe a mano, y solo lo hace quien puede modificar parámetros de costo:
 * entra en el costo de los productos y en la lista de precios (decisión del 2026-09-30).
 */
trait ManualReferencePriceRules
{
    /**
     * @return list<string>
     */
    protected function manualPriceRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'decimal:0,4'];
    }

    /**
     * `has()` y no `filled()`: enviar el precio vacío también lo cambia (lo borra), así que exige el mismo permiso.
     */
    protected function validateManualPrice(Validator $validator, bool $tracksInventory): void
    {
        if (! $this->has('current_price') || $validator->errors()->has('current_price')) {
            return;
        }

        if (! ($this->user()?->can(Permission::CostsUpdate->value) ?? false)) {
            $validator->errors()->add('current_price', __('No tienes permiso para fijar el precio de una materia prima.'));

            return;
        }

        if ($tracksInventory) {
            $validator->errors()->add('current_price', __('Esta materia prima controla inventario: su precio sale de sus compras y no se escribe a mano.'));
        }
    }
}
