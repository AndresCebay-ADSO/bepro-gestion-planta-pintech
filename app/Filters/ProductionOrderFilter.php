<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class ProductionOrderFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'status',
        'dateFrom',
        'dateTo',
        'completedFrom',
        'completedTo',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            if ($this->isValidInteger($value)) {
                $query->orWhere('lot_number', (int) $value);
            }

            $this->applySearchNested($query, [
                'order_number',
                'product.name',
                'product.code',
            ], $value);
        });
    }

    protected function status(string $value): void
    {
        $this->applyExact('status', $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('created_at', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('created_at', null, $value);
    }

    protected function completedFrom(string $value): void
    {
        $this->applyDateRange('completion_date', $value, null);
    }

    protected function completedTo(string $value): void
    {
        $this->applyDateRange('completion_date', null, $value);
    }
}
