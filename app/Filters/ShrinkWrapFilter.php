<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class ShrinkWrapFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'shrinkWrapTypeId',
        'dateFrom',
        'dateTo',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $this->applySearchNested($query, ['productionOrder.order_number', 'shrinkWrapType.name'], $value);
            $this->orWhereLotNumber($query, $value, 'productionOrder');
        });
    }

    protected function shrinkWrapTypeId(string $value): void
    {
        $this->applyExact('shrink_wrap_type_id', (int) $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('wrapped_at', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('wrapped_at', null, $value);
    }
}
