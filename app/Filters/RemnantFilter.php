<?php

declare(strict_types=1);

namespace App\Filters;

class RemnantFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'status',
        'warehouseId',
    ];

    protected function search(string $value): void
    {
        $this->applySearch([
            'product.name',
            'product.code',
            'sourceOrder.order_number',
        ], $value);
    }

    protected function status(string $value): void
    {
        $this->applyExact('status', $value);
    }

    protected function warehouseId(string $value): void
    {
        $this->applyExact('warehouse_id', (int) $value);
    }
}
