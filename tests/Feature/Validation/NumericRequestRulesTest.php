<?php

declare(strict_types=1);

use App\Enums\NumberType;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\SalesOrder;
use App\Models\ShrinkWrapType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;
use Spatie\Permission\Models\Role;
use Tests\Support\NumberTypeColumns;

/*
 * Auditoría 3.6, PR 5 (índice §4.3): recorre las reglas de todos los FormRequest de escritura. Un número sin `max` o sin
 * `decimal` deja pasar lo que su columna no guarda, y PostgreSQL responde con un 500; un ID sin `integer` antes del
 * `exists` llega crudo a la consulta («abc» contra un bigint, también 500). Las reglas se leen, no se ejecutan: este
 * test atrapa el campo que se agregue mañana sin su tope.
 */

uses(RefreshDatabase::class);

/**
 * Dónde se guarda cada número que se digita. Un campo numérico que no esté aquí hace fallar el test: hay que decir en
 * qué columna termina (y su regla no puede aceptar más que ella), o con qué tipo se valida si no se guarda tal cual.
 * `null` = no tiene columna propia (JSON de I+D); igual necesita `decimal` y `max`.
 *
 * @return array<string, array<string, string|NumberType|null>>
 */
function numericRequestFields(): array
{
    $groups = [
        'StoreInventoryMovementRequest' => [
            'quantity' => 'inventory_movements.quantity',
            'cost_price' => 'inventory_movements.cost_price',
        ],
        'StoreRawMaterialRequest|UpdateRawMaterialRequest' => [
            'minimum_stock' => 'raw_materials.minimum_stock',
            'alert_days_before_expiry' => 'raw_materials.alert_days_before_expiry',
            'price_variation_threshold' => 'raw_materials.price_variation_threshold',
            'current_price' => 'raw_materials.current_price',
        ],
        'StoreProductionOrderRequest' => [
            'quantity' => 'production_orders.quantity',
            'packaging.*.planned_units' => 'production_order_packaging_plan.planned_units',
        ],
        'StorePackagingPlanRequest' => [
            'planned_units' => 'production_order_packaging_plan.planned_units',
        ],
        'CompleteProductionOrderRequest|SubmitProductionOrderForReviewRequest|PreviewProductionOrderCostsRequest' => [
            'ingredients.*.actual_quantity' => 'production_order_details.actual_quantity',
            'packaging.*.actual_units' => 'production_order_packaging_plan.actual_units',
            'packaging.*.new_containers_used' => 'production_order_packaging_plan.new_containers_used',
            'packaging.*.labels_used' => 'production_order_packaging_plan.labels_used',
            'remnant_quantity_gallons' => 'production_remnants.original_quantity_gallons',
        ],
        'CompleteProductionOrderRequest|SubmitProductionOrderForReviewRequest' => [
            'viscosity_ku' => 'production_orders.viscosity_ku',
            'grinding_hg' => 'production_orders.grinding_hg',
            'quality_solids' => 'production_orders.quality_solids',
            'actual_yield_quantity' => 'production_orders.actual_quantity',
            'spillage_quantity' => 'production_orders.spillage_quantity',
            'density_kg_per_gallon' => 'production_orders.density_kg_per_gallon',
        ],
        'StoreLineAdjustmentRequest' => [
            'quantity' => 'production_order_line_adjustments.quantity',
        ],
        'ConsumeRemnantRequest' => [
            'quantity_gallons' => 'remnant_consumptions.quantity_gallons',
        ],
        'StoreFormulaRequest|UpdateFormulaRequest' => [
            'details.*.quantity' => 'formula_details.quantity',
        ],
        'StoreProductRequest|UpdateProductRequest' => [
            'cif_percentage' => 'products.cif_percentage',
            'price_threshold' => 'products.price_threshold',
            'quality_viscosity_lower' => 'products.quality_viscosity_lower',
            'quality_viscosity_upper' => 'products.quality_viscosity_upper',
            'quality_fineness_lower' => 'products.quality_fineness_lower',
            'quality_fineness_upper' => 'products.quality_fineness_upper',
            'quality_solids_lower' => 'products.quality_solids_lower',
            'quality_solids_upper' => 'products.quality_solids_upper',
        ],
        'StoreProductVariantRequest|UpdateProductVariantRequest' => [
            'presentation_value' => 'product_variants.presentation_value',
        ],
        'UpdateCostRequest' => [
            'sales_margin' => 'products.sales_margin',
            // No se guarda: se convierte en margen. Se valida como dinero.
            'sales_price' => NumberType::Money,
        ],
        'StoreQuotationRequest|UpdateQuotationRequest|PreviewQuotationPricingRequest' => [
            'iva_percentage' => 'quotations.iva_percentage',
            'items.*.quantity' => 'quotation_items.quantity',
            'items.*.price_adjustment_pct' => 'quotation_items.price_adjustment_pct',
            'items.*.unit_price' => 'quotation_items.unit_price',
        ],
        'StoreSalesOrderRequest' => [
            'items.*.quantity' => 'sales_order_items.quantity',
        ],
        'StoreFinishedInventoryMovementRequest' => [
            'quantity' => 'finished_inventory_movements.quantity',
        ],
        'StoreUnitOfMeasureRequest|UpdateUnitOfMeasureRequest' => [
            'to_kg_conversion' => 'unit_of_measures.to_kg_conversion',
            'to_liter_conversion' => 'unit_of_measures.to_liter_conversion',
        ],
        'StoreShrinkWrapTypeRequest|UpdateShrinkWrapTypeRequest' => [
            'items.*.quantity' => 'shrink_wrap_type_items.quantity',
        ],
        'StoreShrinkWrapRequest' => [
            'applications' => 'shrink_wraps.applications',
        ],
        'StorePaintDevelopmentRequest|UpdatePaintDevelopmentRequest' => [
            'context_payload.temp_min' => null,
            'context_payload.temp_max' => null,
            'performance_payload.adherencia_obj' => null,
            'performance_payload.camara_obj' => null,
            'performance_payload.delta_e' => null,
            'application_payload.temp_aplicacion' => null,
            'application_payload.humedad_aplicacion' => null,
            'application_payload.dft_min' => null,
            'application_payload.dft_max' => null,
            'application_payload.manos' => null,
            'application_payload.pot_life' => null,
            'application_payload.solidos_vol' => null,
            'application_payload.voc' => null,
        ],
    ];

    $map = [];

    foreach ($groups as $classes => $fields) {
        foreach (explode('|', $classes) as $class) {
            $map[$class] = [...($map[$class] ?? []), ...$fields];
        }
    }

    return $map;
}

/**
 * FormRequests de escritura. Los `Index*` validan filtros de listados (los cubrió el transversal de listados).
 *
 * @return list<class-string<FormRequest>>
 */
function numericWriteFormRequests(): array
{
    $classes = [];
    $directory = app_path('Http/Requests');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php' || str_starts_with($file->getBasename(), 'Index')) {
            continue;
        }

        $relative = substr($file->getPathname(), strlen($directory) + 1, -4);
        $class = 'App\\Http\\Requests\\'.str_replace('/', '\\', $relative);

        if (class_exists($class) && is_subclass_of($class, FormRequest::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

/**
 * Reglas del request, con la ruta y los modelos que lee (`$this->route('product')`…).
 *
 * @param  class-string<FormRequest>  $class
 * @param  array<string, mixed>  $parameters
 * @return array<string, list<mixed>>
 */
function numericRequestRules(string $class, array $parameters): array
{
    $request = $class::create('/', 'POST');
    $request->setContainer(app())->setRedirector(app('redirect'));
    $request->setUserResolver(fn () => $parameters['user']);

    $route = new Route('POST', '/', []);
    $route->bind($request);

    foreach ($parameters as $name => $model) {
        $route->setParameter($name, $model);
    }

    $request->setRouteResolver(fn () => $route);

    $rules = app()->call([$request, 'rules']);
    $normalized = [];

    foreach ($rules as $field => $fieldRules) {
        $list = is_string($fieldRules) ? explode('|', $fieldRules) : (is_array($fieldRules) ? $fieldRules : [$fieldRules]);
        $normalized[$field] = array_values($list);
    }

    return $normalized;
}

/**
 * Nombre y parámetros de cada regla de texto (`max:100` → ['max', ['100']]); los objetos por su clase.
 *
 * @param  list<mixed>  $rules
 * @return list<array{0: string, 1: list<string>}>
 */
function numericParsedRules(array $rules): array
{
    return array_map(function (mixed $rule): array {
        if (is_string($rule)) {
            [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);

            return [$name, $parameters === null ? [] : explode(',', $parameters)];
        }

        return match (true) {
            $rule instanceof Exists => ['exists', explode(',', substr((string) $rule, strlen('exists:')))],
            $rule instanceof Enum => ['enum', []],
            $rule instanceof In => ['in', []],
            default => [is_object($rule) ? $rule::class : gettype($rule), []],
        };
    }, $rules);
}

function numericRuleIndex(array $parsed, string $name): ?int
{
    foreach ($parsed as $index => [$ruleName]) {
        if ($ruleName === $name) {
            return $index;
        }
    }

    return null;
}

/** @return list<string> */
function numericRuleParameters(array $parsed, string $name): array
{
    $index = numericRuleIndex($parsed, $name);

    return $index === null ? [] : $parsed[$index][1];
}

/** Un ID: `id`, `*_id` o `*.id`. */
function numericIsIdField(string $field): bool
{
    return (bool) preg_match('/(^|\.)([a-z_]+_)?id$/', $field);
}

/**
 * Decimales que se pueden digitar y máximo de la columna donde se guarda el campo.
 *
 * @return array{0: int, 1: string}
 */
function numericColumnLimits(string|NumberType $target): array
{
    if ($target instanceof NumberType) {
        return [$target->inputScale(), $target->max()];
    }

    $type = NumberTypeColumns::map()[$target] ?? null;

    if ($type !== null) {
        return [$type->inputScale(), $type->max()];
    }

    [$table, $name] = explode('.', $target, 2);
    $column = collect(Schema::getColumns($table))->firstWhere('name', $name);

    expect($column)->not->toBeNull("La columna {$target} no existe");
    expect(strtolower($column['type_name']))->toBeIn(['integer', 'int4', 'int'], "{$target} no está en el catálogo de tipos");

    return [0, NumberType::Units->max()];
}

beforeEach(function (): void {
    $user = User::factory()->create();

    $this->parameters = [
        'user' => $user,
        'client' => Client::factory()->create(),
        'product' => Product::factory()->create(),
        'product_category' => ProductCategory::factory()->create(),
        'production_order' => ProductionOrder::factory()->create(),
        'quotation' => Quotation::factory()->create(),
        'raw_material' => RawMaterial::factory()->create(),
        'raw_material_category' => RawMaterialCategory::factory()->create(),
        'role' => Role::create(['name' => 'Rol de prueba', 'guard_name' => 'web']),
        'sales_order' => SalesOrder::factory()->create(),
        'shrink_wrap_type' => ShrinkWrapType::factory()->create(),
        'unit_of_measure' => UnitOfMeasure::factory()->create(),
        'variant' => ProductVariant::factory()->create(),
        'warehouse' => Warehouse::factory()->create(),
    ];
});

test('los números tienen decimales y tope, y los IDs son enteros antes del exists', function (): void {
    $fields = numericRequestFields();
    $problems = [];

    foreach (numericWriteFormRequests() as $class) {
        $short = class_basename($class);

        foreach (numericRequestRules($class, $this->parameters) as $field => $rules) {
            $parsed = numericParsedRules($rules);
            $names = array_column($parsed, 0);

            // Un campo que no se puede enviar (`prohibited`) no llega a la base.
            if (in_array('prohibited', $names, true)) {
                continue;
            }

            $isNumeric = in_array('numeric', $names, true);
            $isInteger = in_array('integer', $names, true);
            $isChoice = in_array('enum', $names, true) || in_array('in', $names, true);
            $exists = numericRuleIndex($parsed, 'exists');
            $existsOnId = $exists !== null && in_array($parsed[$exists][1][1] ?? 'NULL', ['id', 'NULL'], true);

            // R3: un ID es entero antes de llegar a la base.
            if (numericIsIdField($field) || $existsOnId) {
                $integer = numericRuleIndex($parsed, 'integer');

                if ($integer === null || ($exists !== null && $integer > $exists)) {
                    $problems[] = "{$short}.{$field}: un ID necesita `integer` antes del `exists`";
                }

                continue;
            }

            if (! $isNumeric && (! $isInteger || $isChoice)) {
                continue;
            }

            // R1: un decimal dice cuántos decimales y hasta dónde.
            if ($isNumeric && numericRuleIndex($parsed, 'decimal') === null) {
                $problems[] = "{$short}.{$field}: `numeric` sin `decimal`";
            }

            // R1 y R2: todo número tiene tope.
            if (numericRuleIndex($parsed, 'max') === null) {
                $problems[] = "{$short}.{$field}: sin `max`";
            }

            // R4: dónde se guarda, y que la regla no acepte más que la columna.
            if (! array_key_exists($field, $fields[$short] ?? [])) {
                $problems[] = "{$short}.{$field}: falta en numericRequestFields() (¿en qué columna se guarda?)";

                continue;
            }

            $target = $fields[$short][$field];

            if ($target === null) {
                continue;
            }

            [$scale, $max] = numericColumnLimits($target);
            $decimal = numericRuleParameters($parsed, 'decimal');
            $ruleScale = $isNumeric ? (int) ($decimal[1] ?? $decimal[0] ?? PHP_INT_MAX) : 0;
            $ruleMax = numericRuleParameters($parsed, 'max')[0] ?? null;

            if ($ruleScale > $scale) {
                $problems[] = "{$short}.{$field}: acepta {$ruleScale} decimales y su tipo admite {$scale}";
            }

            if ($ruleMax !== null && bccomp($ruleMax, $max, 6) > 0) {
                $problems[] = "{$short}.{$field}: acepta hasta {$ruleMax} y la columna llega a {$max}";
            }
        }
    }

    expect($problems)->toBe([]);
});

test('cada campo del mapa existe en su request', function (): void {
    $requests = collect(numericWriteFormRequests())->keyBy(fn (string $class): string => class_basename($class));
    $missing = [];

    foreach (numericRequestFields() as $short => $fields) {
        $class = $requests->get($short);

        if ($class === null) {
            $missing[] = "{$short}: no existe";

            continue;
        }

        $rules = numericRequestRules($class, $this->parameters);

        foreach (array_keys($fields) as $field) {
            if (! array_key_exists($field, $rules)) {
                $missing[] = "{$short}.{$field}";
            }
        }
    }

    expect($missing)->toBe([]);
});
