<?php

declare(strict_types=1);

namespace App\Filters;

class QuotationFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status', 'createdBy', 'dateFrom', 'dateTo'];

    protected function search(string $value): void
    {
        $this->applySearch([
            'quotation_number',
            'client_business_name',
            'client_nit',
            'client.business_name',
        ], $value);
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
