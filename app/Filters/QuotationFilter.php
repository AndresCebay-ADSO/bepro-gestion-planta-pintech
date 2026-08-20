<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class QuotationFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status', 'createdBy', 'dateFrom', 'dateTo'];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value) {
            if (is_numeric($value)) {
                $query->orWhere('quotation_number', (int) $value);
            }

            $this->applySearchColumn($query, 'client_business_name', $value);
            $this->applySearchColumn($query, 'client_nit', $value);
            $this->applySearchColumn($query, 'client.business_name', $value);
        });
    }

    protected function status(string $value): void
    {
        $this->applyExact('status', $value);
    }

    protected function createdBy(string $value): void
    {
        $this->applyExact('created_by', (int) $value);
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('quotation_date', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('quotation_date', null, $value);
    }
}
