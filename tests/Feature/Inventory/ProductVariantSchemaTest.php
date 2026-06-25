<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('el esquema incluye tabla de variantes de producto', function () {
    expect(Schema::hasTable('product_variants'))->toBeTrue();

    expect(Schema::hasColumns('product_variants', [
        'id',
        'product_id',
        'code',
        'name',
        'unit_of_measure_id',
        'presentation_value',
        'presentation_label',
    ]))->toBeTrue();

    $columns = DB::select("PRAGMA table_info('product_variants')");
    $nameColumn = collect($columns)->firstWhere('name', 'name');
    expect((bool) $nameColumn->notnull)->toBeTrue();
});

test('las tablas operativas incluyen product_variant_id para migracion gradual', function () {
    expect(Schema::hasColumn('finished_inventories', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('finished_inventory_movements', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('transfers', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('price_lists', 'product_variant_id'))->toBeTrue();
});
