<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class PaintDevelopmentRequestFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status', 'dateFrom', 'dateTo'];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            if ($this->isValidInteger($value)) {
                $query->orWhere('request_number', (int) $value);
            }

            $this->applySearchNested($query, [
                'project_name',
                'client_name',
            ], $value);
        });
    }

    protected function status(string $value): void
    {
        $this->applyExact('status', $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('sample_due_date', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('sample_due_date', null, $value);
    }
}
