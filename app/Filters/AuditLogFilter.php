<?php

declare(strict_types=1);

namespace App\Filters;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AuditLogFilter extends QueryFilter
{
    protected array $filterable = [
        'search',
        'logName',
        'event',
        'dateFrom',
        'dateTo',
    ];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $query->whereRaw('LOWER(description) LIKE LOWER(?)', ['%'.$value.'%'])
                ->orWhereRaw('LOWER(event) LIKE LOWER(?)', ['%'.$value.'%'])
                ->orWhereRaw('LOWER(log_name) LIKE LOWER(?)', ['%'.$value.'%'])
                ->orWhereHasMorph('causer', [User::class], function (Builder $uq) use ($value): void {
                    $uq->whereRaw('LOWER(name) LIKE LOWER(?)', ['%'.$value.'%'])
                        ->orWhereRaw('LOWER(email) LIKE LOWER(?)', ['%'.$value.'%']);
                });
        });
    }

    protected function logName(string $value): void
    {
        if ($value !== 'all') {
            $this->applyExact('log_name', $value);
        }
    }

    protected function event(string $value): void
    {
        if ($value !== 'all') {
            $this->applyExact('event', $value);
        }
    }

    protected function dateFrom(string $value): void
    {
        $this->applyDateRange('created_at', $value, null);
    }

    protected function dateTo(string $value): void
    {
        $this->applyDateRange('created_at', null, $value);
    }
}
