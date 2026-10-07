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

    public const MAX_PG_INTEGER = 2147483647;

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

    /**
     * @param  list<string>  $columns
     * @param  list<string>  $exactIntegerColumns
     */
    protected function applySearch(array $columns, string $value, array $exactIntegerColumns = []): void
    {
        $this->builder->where(function (Builder $query) use ($columns, $value, $exactIntegerColumns) {
            $this->applySearchNested($query, $columns, $value, $exactIntegerColumns);
        });
    }

    /**
     * Busca `$value` en columnas propias (`order_number`) o de relaciones (`product.name`, y anidadas:
     * `batch.productionOrder.order_number`) con `LOWER() LIKE`. `$exactIntegerColumns` (`lot_number`,
     * `productionOrder.lot_number`) se comparan como entero exacto y solo si el texto es un entero, porque `LOWER()`
     * sobre un entero falla en PostgreSQL. Las columnas de una misma relación van en una sola subconsulta.
     *
     * @param  list<string>  $columns
     * @param  list<string>  $exactIntegerColumns
     */
    protected function applySearchNested(Builder $query, array $columns, string $value, array $exactIntegerColumns = []): void
    {
        $integer = $this->isValidInteger($value) ? (int) $value : null;

        // [relación => [[columna, ¿exacta?], ...]]; '' agrupa las columnas propias del modelo.
        $conditionsByRelation = [];

        foreach ([...array_map(fn ($c) => [$c, false], $columns), ...array_map(fn ($c) => [$c, true], $exactIntegerColumns)] as [$column, $exact]) {
            if (! preg_match('/^[a-zA-Z0-9_.]+$/', $column)) {
                throw new \InvalidArgumentException("Invalid column name: {$column}");
            }

            if ($exact && $integer === null) {
                continue;
            }

            $relation = str_contains($column, '.') ? Str::beforeLast($column, '.') : '';
            $conditionsByRelation[$relation][] = [Str::afterLast($column, '.'), $exact];
        }

        $match = function (Builder $builder, array $conditions) use ($value, $integer): void {
            foreach ($conditions as [$column, $exact]) {
                $exact
                    ? $builder->orWhere($column, $integer)
                    : $builder->orWhereRaw('LOWER('.$column.') LIKE LOWER(?)', ['%'.$value.'%']);
            }
        };

        foreach ($conditionsByRelation as $relation => $conditions) {
            if ($relation === '') {
                $match($query, $conditions);

                continue;
            }

            $query->orWhereHas($relation, fn (Builder $related) => $related->where(
                fn (Builder $nested) => $match($nested, $conditions)
            ));
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

    /**
     * Filtro de estado de los datos maestros (docs/POLITICA_ELIMINACION.md): `active`, `inactive` o `all` (sin filtro).
     */
    protected function applyActiveStatus(string $value): void
    {
        match ($value) {
            'active' => $this->builder->where('is_active', true),
            'inactive' => $this->builder->where('is_active', false),
            default => null,
        };
    }

    protected function applyExact(string $column, mixed $value): void
    {
        $this->builder->where($column, $value);
    }

    protected function isValidInteger(mixed $value): bool
    {
        $stringVal = (string) $value;

        return preg_match('/^\d+$/', $stringVal) === 1
            && (float) $stringVal <= self::MAX_PG_INTEGER;
    }
}
