<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rule;

/**
 * Regla para los campos que eligen un valor de catálogo con `is_active` (unidad de medida, categoría).
 *
 * Al crear, solo un valor activo. Al editar, el registro conserva el suyo aunque se haya desactivado; si lo cambia, el
 * nuevo debe estar activo. Así editar otro campo no obliga a cambiar de unidad o de categoría
 * (docs/POLITICA_ELIMINACION.md).
 */
trait CatalogSelectionRules
{
    /**
     * @param  string  $table  Tabla del catálogo (`unit_of_measures`, `product_categories`…).
     * @param  string  $field  Campo del formulario que guarda el id elegido.
     * @param  int|null  $currentId  El valor que tiene hoy el registro que se edita; `null` al crear.
     * @return list<mixed>
     */
    protected function activeOrCurrentRules(string $table, string $field, ?int $currentId = null): array
    {
        $keepsCurrent = $currentId !== null && (int) $this->input($field) === $currentId;

        return [
            'bail',
            'required',
            'integer',
            Rule::exists($table, 'id')->when(! $keepsCurrent, fn ($rule) => $rule->where('is_active', true)),
        ];
    }
}
