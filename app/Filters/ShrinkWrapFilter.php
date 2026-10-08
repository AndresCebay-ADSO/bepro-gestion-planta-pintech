<?php

declare(strict_types=1);

namespace App\Filters;

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
        $this->applySearch(
            ['productionOrder.order_number', 'productionOrder.color', 'shrinkWrapType.name'],
            $value,
            exactIntegerColumns: ['productionOrder.lot_number'],
        );
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
