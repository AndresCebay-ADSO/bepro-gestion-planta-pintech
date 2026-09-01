<?php

declare(strict_types=1);

namespace App\Filters;

class AlertFilter extends QueryFilter
{
    protected array $filterable = [
        'status',
        'type',
        'severity',
    ];

    protected function status(string $value): void
    {
        match ($value) {
            'active' => $this->builder->where('is_resolved', false),
            'resolved' => $this->builder->where('is_resolved', true),
            default => null, // 'all' — no filter on is_resolved
        };
    }

    protected function type(string $value): void
    {
        $this->applyExact('type', $value);
    }

    protected function severity(string $value): void
    {
        $this->applyExact('severity', $value);
    }
}
