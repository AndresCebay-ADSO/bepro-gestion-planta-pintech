<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('el esquema incluye tabla de variantes de producto', function () {
    expect(Schema::hasTable('product_variants'))->toBeTrue();

    expect(Schema::hasColumns('product_variants', [
        'id',
        'product_id',
        'sku',
        'unit_of_measure_id',
        'presentation_value',
        'presentation_label',
        'color',
        'finish',
        'base_type',
        'component_system',
    ]))->toBeTrue();
});

test('las tablas operativas incluyen product_variant_id para migracion gradual', function () {
    expect(Schema::hasColumn('finished_inventories', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('finished_inventory_movements', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('transfers', 'product_variant_id'))->toBeTrue();
    expect(Schema::hasColumn('price_lists', 'product_variant_id'))->toBeTrue();
});
