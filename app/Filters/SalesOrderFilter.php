<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class SalesOrderFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status', 'priority', 'dateFrom', 'dateTo'];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            if (is_numeric($value)) {
                $query->orWhere('id', (int) $value);
            }

            $this->applySearchColumn($query, 'client.business_name', $value);
        });
    }

    protected function status(string $value): void
    {
        $this->applyExact('status', $value);
    }

    protected function priority(string $value): void
    {
        $this->applyExact('priority', $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('required_date', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('required_date', null, $value);
    }
}
