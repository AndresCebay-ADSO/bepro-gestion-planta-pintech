<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class PriceListFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $this->applySearchNested($query, [
                'name',
                'code',
                'variants.name',
                'variants.code',
                'variants.presentation_label',
            ], $value);
        });
    }
}
