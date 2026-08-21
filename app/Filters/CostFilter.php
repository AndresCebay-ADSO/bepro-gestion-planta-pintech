<?php

declare(strict_types=1);

namespace App\Filters;

class CostFilter extends QueryFilter
{
    protected array $filterable = ['search'];

    protected function search(string $value): void
    {
        $this->applySearch(['name', 'code'], $value);
    }
}
