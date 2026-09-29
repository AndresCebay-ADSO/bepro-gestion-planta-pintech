<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Para catálogos con `is_active` que se eligen en formularios (unidades, categorías).
 */
trait SelectableWhenActive
{
    public function scopeActive(Builder $query): void
    {
        $query->where($this->qualifyColumn('is_active'), true);
    }

    /**
     * Lo que se puede elegir en un formulario: lo activo, más lo que ya usa el registro que se edita (algo desactivado se
     * conserva donde estaba, pero no se ofrece para registros nuevos).
     *
     * @param  array<int, int|null>  $keepIds
     */
    public function scopeSelectable(Builder $query, array $keepIds = []): void
    {
        $keepIds = array_values(array_filter($keepIds));

        $query->where(fn (Builder $q) => $q
            ->where($this->qualifyColumn('is_active'), true)
            ->when($keepIds !== [], fn (Builder $q) => $q->orWhereIn($this->qualifyColumn('id'), $keepIds)));
    }
}
