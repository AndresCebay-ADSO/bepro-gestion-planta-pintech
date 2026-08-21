<?php

declare(strict_types=1);

namespace App\Filters;

class ClientFilter extends QueryFilter
{
    protected array $filterable = ['search'];

    protected function search(string $value): void
    {
        $this->applySearch(['business_name', 'nit'], $value);
    }
}
