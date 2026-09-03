<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class InventoryMovementFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'type',
        'warehouseId',
        'dateFrom',
        'dateTo',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $this->applySearchNested($query, [
                'rawMaterial.code',
                'batch.lot_number',
            ], $value);
        });
    }

    protected function type(string $value): void
    {
        $this->applyExact('type', $value);
    }

    protected function warehouseId(string $value): void
    {
        $this->applyExact('warehouse_id', (int) $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('movement_date', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('movement_date', null, $value);
    }
}
