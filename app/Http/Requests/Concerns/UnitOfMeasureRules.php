<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Regla de `unit_of_measure_id` para los formularios que eligen una unidad (docs/POLITICA_ELIMINACION.md §7).
 *
 * Al crear, solo una unidad activa. Al editar, el registro conserva su unidad aunque se haya desactivado; si la
 * cambia, la nueva debe estar activa. Así editar otro campo no obliga a cambiar de unidad.
 */
trait UnitOfMeasureRules
{
    /**
     * @param  int|null  $currentUnitId  La unidad que tiene hoy el registro que se edita; `null` al crear.
     * @return list<mixed>
     */
    protected function unitOfMeasureRules(?int $currentUnitId = null): array
    {
        $keepsCurrent = $currentUnitId !== null && (int) $this->input('unit_of_measure_id') === $currentUnitId;

        return [
            'bail',
            'required',
            'integer',
            Rule::exists('unit_of_measures', 'id')->when(! $keepsCurrent, fn ($rule) => $rule->where('is_active', true)),
        ];
    }
}
