<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class FinishedInventoryFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'warehouseId',
        'productId',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $this->applySearchNested($query, [
                'product.code',
                'product.name',
                'productVariant.code',
                'productVariant.name',
                'warehouse.name',
            ], $value);
        });
    }

    protected function warehouseId(string $value): void
    {
        $this->applyExact('warehouse_id', (int) $value);
    }

    protected function productId(string $value): void
    {
        $this->applyExact('product_id', (int) $value);
    }
}
