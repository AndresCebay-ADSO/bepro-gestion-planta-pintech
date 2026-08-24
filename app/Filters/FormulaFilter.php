<?php

declare(strict_types=1);

namespace App\Filters;

class FormulaFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status'];

    protected function search(string $value): void
    {
        $this->applySearch(['product.code', 'product.name'], $value);
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
