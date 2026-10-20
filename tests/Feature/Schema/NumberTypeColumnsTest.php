<?php

declare(strict_types=1);

use App\Enums\NumberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\NumberTypeColumns;

uses(RefreshDatabase::class);

/**
 * @return array<string, NumberType>
 */
function numberTypeColumns(): array
{
    return NumberTypeColumns::map();
}

/**
 * @return array<string, mixed>
 */
function schemaColumn(string $qualified): array
{
    [$table, $name] = explode('.', $qualified, 2);
    $column = collect(Schema::getColumns($table))->firstWhere('name', $name);

    expect($column)->not->toBeNull("Falta la columna {$qualified}");

    return $column;
}

test('cada columna numérica tiene el tamaño de su tipo', function (): void {
    $isPostgres = DB::connection()->getDriverName() === 'pgsql';

    foreach (numberTypeColumns() as $qualified => $type) {
        $column = schemaColumn($qualified);

        if ($type === NumberType::Units) {
            expect(strtolower($column['type_name']))->toBeIn(['integer', 'int4'], "{$qualified} debe ser entera");

            continue;
        }

        expect(strtolower($column['type_name']))->toBe('numeric', "{$qualified} debe ser numeric");

        // SQLite no guarda precisión ni escala: el tamaño solo se puede comprobar en PostgreSQL (job del CI).
        if ($isPostgres) {
            expect($column['type'])->toBe(
                "numeric({$type->precision()},{$type->scale()})",
                "{$qualified} debe ser {$type->name}",
            );
        }
    }
});

test('ninguna columna numeric queda fuera del catálogo', function (): void {
    $catalog = numberTypeColumns();
    $missing = [];

    foreach (Schema::getTables() as $table) {
        if (($table['schema'] ?? null) !== null && ! in_array($table['schema'], ['public', 'main'], true)) {
            continue;
        }

        foreach (Schema::getColumns($table['name']) as $column) {
            $qualified = $table['name'].'.'.$column['name'];

            if (strtolower($column['type_name']) === 'numeric' && ! array_key_exists($qualified, $catalog)) {
                $missing[] = $qualified;
            }
        }
    }

    expect($missing)->toBe([]);
});

test('las columnas y la tabla que no se usaban ya no existen', function (): void {
    expect(Schema::hasColumn('products', 'code'))->toBeFalse()
        ->and(Schema::hasColumn('products', 'brand'))->toBeFalse()
        ->and(Schema::hasTable('price_lists'))->toBeFalse();
});

test('la dirección del pedido cabe en 255 caracteres, como la del cliente', function (): void {
    foreach (['clients.shipping_address', 'sales_orders.shipping_address'] as $qualified) {
        $column = schemaColumn($qualified);

        expect(strtolower($column['type_name']))->toBeIn(['varchar', 'character varying'], "{$qualified} debe ser varchar");

        if (DB::connection()->getDriverName() === 'pgsql') {
            expect($column['type'])->toBe('character varying(255)');
        }
    }
});
