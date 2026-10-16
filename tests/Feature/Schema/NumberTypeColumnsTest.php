<?php

declare(strict_types=1);

use App\Enums\NumberType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Cada columna numérica de negocio con su tipo del catálogo (auditoría 3.6, PR 4). Una columna nueva que no esté aquí
 * hace fallar el test: hay que decidir su tipo antes de crearla.
 *
 * @return array<string, NumberType>
 */
function numberTypeColumns(): array
{
    $map = [];
    $add = function (NumberType $type, array $columns) use (&$map): void {
        foreach ($columns as $column) {
            $map[$column] = $type;
        }
    };

    $add(NumberType::Quantity, [
        'formula_details.quantity',
        'inventory_batches.initial_quantity', 'inventory_batches.remaining_quantity',
        'inventory_movements.quantity',
        'production_order_details.planned_quantity', 'production_order_details.actual_quantity',
        'production_order_line_adjustments.quantity',
        'production_orders.quantity', 'production_orders.actual_quantity', 'production_orders.yield_real_quantity',
        'production_orders.yield_theoretical_quantity', 'production_orders.yield_variance_quantity',
        'production_orders.spillage_quantity',
        'production_remnants.original_quantity_gallons', 'production_remnants.original_quantity_kg',
        'production_remnants.available_quantity_gallons', 'production_remnants.available_quantity_kg',
        'remnant_consumptions.quantity_gallons', 'remnant_consumptions.quantity_kg',
        'raw_materials.minimum_stock',
        'product_variants.presentation_value',
        'shrink_wrap_items.quantity_per_application', 'shrink_wrap_items.quantity',
        'shrink_wrap_type_items.quantity',
    ]);

    $add(NumberType::Units, [
        'production_order_packaging_plan.planned_units', 'production_order_packaging_plan.actual_units',
        'production_order_packaging_plan.new_containers_used', 'production_order_packaging_plan.labels_used',
        'quotation_items.quantity',
        'sales_order_items.quantity',
        'finished_inventories.quantity',
        'finished_product_batches.initial_quantity',
        'finished_product_batch_stocks.quantity',
        'finished_inventory_movements.quantity',
    ]);

    $add(NumberType::Money, [
        'production_order_details.unit_cost', 'production_order_details.total_cost',
        'production_costs.cost', 'production_costs.unit_cost',
        'inventory_batches.unit_price',
        'inventory_movements.cost_price',
        'finished_inventory_movements.cost_price',
        'raw_materials.current_price', 'raw_materials.previous_price',
        'products.current_cost', 'products.current_price',
        'product_variants.current_cost', 'product_variants.current_price',
        'production_remnants.cost_per_gallon',
        'remnant_consumptions.consumed_cost',
        'shrink_wraps.total_cost', 'shrink_wrap_items.total_cost',
        'quotations.subtotal', 'quotations.iva_amount', 'quotations.total',
        'quotation_items.list_unit_price', 'quotation_items.unit_price', 'quotation_items.subtotal',
    ]);

    $add(NumberType::Percentage, [
        'products.cif_percentage', 'products.sales_margin', 'products.price_threshold',
        'quotations.iva_percentage',
        'quotation_items.price_adjustment_pct',
        'raw_materials.price_variation_threshold',
        'production_orders.yield_percentage',
    ]);

    $add(NumberType::ComputedPercentage, ['production_costs.variation_percentage']);

    $add(NumberType::Factor, [
        'unit_of_measures.to_kg_conversion', 'unit_of_measures.to_liter_conversion',
        'production_order_details.conversion_factor',
    ]);

    $add(NumberType::Density, [
        'production_orders.density_kg_per_gallon',
        'production_remnants.density_kg_per_gallon',
    ]);

    $add(NumberType::Quality, [
        'products.quality_viscosity_lower', 'products.quality_viscosity_upper',
        'products.quality_fineness_lower', 'products.quality_fineness_upper',
        'products.quality_solids_lower', 'products.quality_solids_upper',
        'production_orders.viscosity_ku', 'production_orders.grinding_hg', 'production_orders.quality_solids',
    ]);

    return $map;
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
