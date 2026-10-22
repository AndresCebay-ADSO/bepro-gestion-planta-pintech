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
            $like = self::likeContains($value);

            $query->whereRaw(self::likeCondition('description'), [$like])
                ->orWhereRaw(self::likeCondition('event'), [$like])
                ->orWhereRaw(self::likeCondition('log_name'), [$like])
                ->orWhereHasMorph('causer', [User::class], function (Builder $uq) use ($like): void {
                    $uq->whereRaw(self::likeCondition('name'), [$like])
                        ->orWhereRaw(self::likeCondition('email'), [$like]);
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
