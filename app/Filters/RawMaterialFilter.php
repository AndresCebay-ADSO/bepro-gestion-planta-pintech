<?php

declare(strict_types=1);

namespace App\Filters;

class RawMaterialFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status'];

    protected function search(string $value): void
    {
        $this->applySearch(['code'], $value);
    }

    protected function status(string $value): void
    {
        match ($value) {
            'active' => $this->builder->where('is_active', true),
            'inactive' => $this->builder->where('is_active', false),
            default => null, // 'all' — sin filtro
        };
    }
}
