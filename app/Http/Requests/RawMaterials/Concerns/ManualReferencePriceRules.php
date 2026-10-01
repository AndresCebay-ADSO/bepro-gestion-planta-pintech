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
    /** Cambiar el control de inventario cambia cómo se costea: sin él, el precio se escribe a mano. */
    public const TRACKING_PERMISSION_MESSAGE = 'Solo quien puede modificar los parámetros de costo cambia el control de inventario: sin él, el precio se escribe a mano y entra en los costos.';

    /**
     * @return list<string>
     */
    protected function manualPriceRules(): array
    {
        // La columna es decimal(12,4): sin tope, PostgreSQL rechaza el valor con un error del servidor.
        return ['nullable', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999'];
    }

    /**
     * @param  bool  $priceRequired  La materia prima empieza a no controlar inventario (se crea así o se le quita el
     *                               control): necesita un precio, aunque sea 0 escrito a propósito. Vacío, los costos la
     *                               tomarían como 0 sin que nadie lo decidiera.
     */
    protected function validateManualPrice(Validator $validator, bool $tracksInventory, bool $priceRequired): void
    {
        if ($validator->errors()->has('current_price')) {
            return;
        }

        // `has()` y no `filled()`: enviar el precio vacío también lo cambia (lo borraría).
        $sent = $this->has('current_price');
        $missing = $this->input('current_price') === null || $this->input('current_price') === '';

        if ($tracksInventory) {
            if ($sent) {
                $validator->errors()->add('current_price', __('Esta materia prima controla inventario: su precio sale de sus compras y no se escribe a mano.'));
            }

            return;
        }

        $canUpdateCosts = $this->user()?->can(Permission::CostsUpdate->value) ?? false;

        if ($sent && ! $canUpdateCosts) {
            $validator->errors()->add('current_price', __('No tienes permiso para fijar el precio de una materia prima.'));

            return;
        }

        if ($priceRequired && ! $canUpdateCosts) {
            $validator->errors()->add('tracks_inventory', __(self::TRACKING_PERMISSION_MESSAGE));

            return;
        }

        if (($priceRequired || $sent) && $missing) {
            $validator->errors()->add('current_price', __('Escribe el precio de referencia: sin control de inventario es el que usan los costos. Puede ser 0.'));
        }
    }
}
