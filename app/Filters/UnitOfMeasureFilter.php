<?php

declare(strict_types=1);

namespace App\Filters;

class UnitOfMeasureFilter extends QueryFilter
{
    protected array $filterable = ['search', 'status'];

    protected function search(string $value): void
    {
        $this->applySearch(['code', 'name', 'symbol'], $value);
    }

    protected function status(string $value): void
    {
        $this->applyActiveStatus($value);
    }
}
