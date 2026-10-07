<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

class QrCodeFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status'];

    protected function search(string $value): void
    {
        $this->builder->where(function (Builder $query) use ($value): void {
            $this->applySearchNested($query, ['token', 'product.name', 'product.code', 'productionOrder.order_number'], $value);
            $this->orWhereLotNumber($query, $value, 'productionOrder');
        });
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
