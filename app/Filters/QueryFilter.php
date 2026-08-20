<?php

declare(strict_types=1);

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

abstract class QueryFilter
{
    protected Builder $builder;

    protected FormRequest $request;

    protected array $filterable = [];

    public function __construct(FormRequest $request)
    {
        $this->request = $request;
    }

    public function apply(Builder $builder): Builder
    {
        $this->builder = $builder;

        foreach ($this->filters() as $name => $value) {
            $method = Str::camel($name);
            if (in_array($method, $this->filterable, true) && method_exists($this, $method)) {
                $this->$method($value);
            }
        }

        return $this->builder;
    }

    public function filters(): array
    {
        $validated = array_map(function ($value) {
            if (is_string($value)) {
                return preg_replace('/\s+/', ' ', trim($value));
            }

            return $value;
        }, $this->request->validated());

        return array_filter(
            $validated,
            fn ($value) => $value !== '' && $value !== null
        );
    }

    protected function applySearch(array $columns, string $value): void
    {
        $this->builder->where(function (Builder $query) use ($columns, $value) {
            foreach ($columns as $column) {
                $this->applySearchColumn($query, $column, $value);
            }
        });
    }

    protected function applySearchColumn(Builder $query, string $column, string $value): void
    {
        if (! preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
            throw new \InvalidArgumentException("Invalid column name: {$column}");
        }

        if (str_contains($column, '.')) {
            [$relation, $relationColumn] = explode('.', $column, 2);
            $query->orWhereHas($relation, fn (Builder $q) => $q->whereRaw('LOWER('.$relationColumn.') LIKE LOWER(?)', ['%'.$value.'%']));
        } else {
            $query->orWhereRaw('LOWER('.$column.') LIKE LOWER(?)', ['%'.$value.'%']);
        }
    }

    protected function applyDateRange(string $column, ?string $from, ?string $to): void
    {
        if ($from) {
            $this->builder->whereDate($column, '>=', $from);
        }
        if ($to) {
            $this->builder->whereDate($column, '<=', $to);
        }
    }

    protected function applyExact(string $column, mixed $value): void
    {
        $this->builder->where($column, $value);
    }
}
