<?php

declare(strict_types=1);

namespace App\Filters;

use App\Enums\SystemRole;
use Illuminate\Database\Eloquent\Builder;

class RoleFilter extends QueryFilter
{
    protected array $filterable = ['search'];

    /**
     * Busca por nombre y, en los roles del sistema, también por la etiqueta que muestra la tabla ("Administrador").
     */
    protected function search(string $value): void
    {
        $normalized = mb_strtolower($value);
        $systemNames = array_map(
            fn (SystemRole $role): string => $role->value,
            array_filter(
                SystemRole::cases(),
                fn (SystemRole $role): bool => str_contains(mb_strtolower($role->label()), $normalized),
            ),
        );

        $this->builder->where(function (Builder $query) use ($value, $systemNames): void {
            $this->applySearchNested($query, ['name'], $value);

            if ($systemNames !== []) {
                $query->orWhereIn('name', array_values($systemNames));
            }
        });
    }
}
