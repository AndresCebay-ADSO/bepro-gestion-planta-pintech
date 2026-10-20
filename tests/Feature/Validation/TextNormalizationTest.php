<?php

declare(strict_types=1);

/*
 * Auditoría 3.6, PR 8: códigos y nombres normalizados. Dos textos que el usuario lee como el mismo («rm-100» y
 * «RM-100», «Planta  Cali» y «planta cali») chocan con la regla del formulario y, de respaldo, con el índice de la base.
 * Casos de tests/Audit (RawMaterials, Products, Warehouses, Catalogs, Clients) convertidos en tests reales.
 */

use App\Enums\SystemRole;
use App\Models\Client;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\ShrinkWrapType;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole(SystemRole::SuperAdmin->value);

    $this->uom = UnitOfMeasure::factory()->create();
    $this->rawMaterialCategory = RawMaterialCategory::factory()->create(['code' => 'RES', 'name' => 'Resinas']);
    $this->productCategory = ProductCategory::factory()->create(['name' => 'Esmaltes Base']);
    $this->rawMaterial = RawMaterial::factory()->create(['code' => 'MP-100', 'category_id' => $this->rawMaterialCategory->id, 'unit_of_measure_id' => $this->uom->id]);
    $this->product = Product::factory()->create(['name' => 'BP Esmalte Base', 'category_id' => $this->productCategory->id, 'unit_of_measure_id' => $this->uom->id]);
    $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id, 'code' => 'VAR-1', 'package_raw_material_id' => null, 'label_raw_material_id' => null]);
    $this->warehouse = Warehouse::factory()->storage()->create(['name' => 'Planta Cali']);
    $this->shrinkWrapType = ShrinkWrapType::factory()->create(['name' => 'Galón x4']);
    $this->bag = RawMaterial::factory()->secondaryPackaging()->create();
    $this->client = Client::factory()->create(['nit' => '901674872']);
});

/**
 * Ruta y datos válidos para crear un registro de cada módulo, con `$overrides` encima.
 *
 * @return array{0: string, 1: array<string, mixed>}
 */
function textNormalizationCreate(string $module, array $overrides = []): array
{
    $test = test();

    [$route, $payload] = match ($module) {
        'raw-material' => [route('raw-materials.store'), [
            'code' => 'RM-NUEVA', 'category_id' => $test->rawMaterialCategory->id, 'unit_of_measure_id' => $test->uom->id,
            'minimum_stock' => '10', 'alert_days_before_expiry' => 30, 'tracks_inventory' => true, 'is_active' => true,
        ]],
        'product' => [route('products.store'), [
            'name' => 'Producto Nuevo', 'category_id' => $test->productCategory->id, 'unit_of_measure_id' => $test->uom->id,
            'cif_percentage' => '10', 'price_threshold' => '5',
        ]],
        'variant' => [route('products.variants.store', $test->product), [
            'code' => 'VAR-NUEVA', 'name' => 'Galón', 'unit_of_measure_id' => $test->uom->id, 'presentation_value' => '1',
        ]],
        'warehouse' => [route('warehouses.store'), ['name' => 'Bodega Pasto', 'city' => 'Pasto', 'type' => 'storage']],
        'product-category' => [route('catalogs.product-categories.store'), ['name' => 'Lacas']],
        'raw-material-category' => [route('catalogs.raw-material-categories.store'), ['code' => 'SOL', 'name' => 'Solventes', 'type' => 'chemical']],
        'shrink-wrap-type' => [route('catalogs.shrink-wrap-types.store'), [
            'name' => 'Cuñete x2', 'items' => [['raw_material_id' => $test->bag->id, 'quantity' => '1']],
        ]],
        'client' => [route('clients.store'), ['business_name' => 'Cliente Nuevo', 'nit' => '900123456']],
    };

    return [$route, array_merge($payload, $overrides)];
}

test('rechaza el duplicado que solo cambia en mayúsculas, espacios o tildes', function (string $module, string $field, string $value): void {
    [$route, $payload] = textNormalizationCreate($module, [$field => $value]);

    $this->actingAs($this->admin)
        ->postJson($route, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);
})->with([
    'código de MP en minúsculas (RM-01)' => ['raw-material', 'code', 'mp-100'],
    'código de MP con espacios' => ['raw-material', 'code', ' mp-100 '],
    'código de presentación en minúsculas (PRD-03)' => ['variant', 'code', 'var-1'],
    'nombre de producto en minúsculas' => ['product', 'name', 'bp esmalte base'],
    'nombre de producto con espacios dobles' => ['product', 'name', 'BP  ESMALTE   BASE'],
    'bodega en minúsculas (BOD-02)' => ['warehouse', 'name', 'planta cali'],
    'bodega con espacios dobles (BOD-02)' => ['warehouse', 'name', 'Planta  Cali'],
    'categoría de producto en mayúsculas' => ['product-category', 'name', 'ESMALTES BASE'],
    'categoría de producto con espacios dobles (CAT-01)' => ['product-category', 'name', 'Esmaltes  Base'],
    'categoría de MP: código en minúsculas' => ['raw-material-category', 'code', 'res'],
    'categoría de MP: nombre con espacios dobles' => ['raw-material-category', 'name', 'resinas  '],
    'tipo de termoencogido con espacios dobles' => ['shrink-wrap-type', 'name', 'galón  x4'],
    'NIT con espacios alrededor' => ['client', 'nit', ' 901674872 '],
]);

test('guarda el valor normalizado', function (string $module, string $field, string $value, string $stored, string $model): void {
    [$route, $payload] = textNormalizationCreate($module, [$field => $value]);

    $this->actingAs($this->admin)
        ->post($route, $payload)
        ->assertSessionHasNoErrors();

    expect($model::query()->where($field, $stored)->exists())->toBeTrue();
})->with([
    'código de MP' => ['raw-material', 'code', 'rm  200', 'RM 200', RawMaterial::class],
    'código con ñ' => ['raw-material', 'code', 'env-m-cuñ', 'ENV-M-CUÑ', RawMaterial::class],
    'código de presentación' => ['variant', 'code', 'gal-blanco', 'GAL-BLANCO', ProductVariant::class],
    'nombre de producto en mayúsculas (P8-3)' => ['product', 'name', 'Bp  Laca Poliuretánica', 'BP LACA POLIURETÁNICA', Product::class],
    'nombre de producto con tilde partida' => ['product', 'name', "bp primer epo\u{0301}xico", 'BP PRIMER EPÓXICO', Product::class],
    'bodega conserva sus mayúsculas' => ['warehouse', 'name', '  Bodega   Neiva ', 'Bodega Neiva', Warehouse::class],
    'categoría de producto' => ['product-category', 'name', 'Pinturas  de Caucho', 'Pinturas de Caucho', ProductCategory::class],
    'código de categoría de MP (P8-7)' => ['raw-material-category', 'code', 'env  metal', 'ENV METAL', RawMaterialCategory::class],
    'tipo de termoencogido' => ['shrink-wrap-type', 'name', 'Cuñete  x2', 'Cuñete x2', ShrinkWrapType::class],
    'NIT como texto (P8-8)' => ['client', 'nit', '901.674.872-1', '901.674.872-1', Client::class],
]);

test('rechaza los caracteres que no admite el formato, con el mensaje en español', function (string $module, string $field, string $value): void {
    [$route, $payload] = textNormalizationCreate($module, [$field => $value]);

    $response = $this->actingAs($this->admin)
        ->postJson($route, $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect($response->json("errors.{$field}.0"))->toContain('solo admite');
})->with([
    'código de MP con emoji (RM-03)' => ['raw-material', 'code', 'RM/😀'],
    'código de MP con barra invertida' => ['raw-material', 'code', 'RM\\100'],
    'código de presentación con comillas' => ['variant', 'code', 'ENV/1 "4"'],
    'código de categoría de MP con comillas' => ['raw-material-category', 'code', 'RES"'],
    'nombre de producto con barra invertida (PRD-05)' => ['product', 'name', 'EXTRA\\FINO'],
    'nombre de producto con emoji (PRD-05)' => ['product', 'name', 'Esmalte 😀'],
]);

test('el nombre de producto admite la barra (caso 4, D-PRD2)', function (): void {
    [$route, $payload] = textNormalizationCreate('product', ['name' => 'Caoba / Chocolate']);

    $this->actingAs($this->admin)->post($route, $payload)->assertSessionHasNoErrors();

    expect(Product::query()->where('name', 'CAOBA / CHOCOLATE')->exists())->toBeTrue();
});

test('al editar se ignora el propio registro', function (): void {
    $this->actingAs($this->admin);

    $this->put(route('raw-materials.update', $this->rawMaterial), [
        'code' => 'mp-100', 'category_id' => $this->rawMaterialCategory->id, 'unit_of_measure_id' => $this->uom->id,
        'minimum_stock' => '10', 'alert_days_before_expiry' => 30, 'is_active' => true,
    ])->assertSessionHasNoErrors();

    $this->put(route('products.update', $this->product), [
        'name' => 'bp esmalte base', 'category_id' => $this->productCategory->id, 'unit_of_measure_id' => $this->uom->id,
        'cif_percentage' => '10', 'price_threshold' => '5',
    ])->assertSessionHasNoErrors();

    $this->put(route('warehouses.update', $this->warehouse), ['name' => 'PLANTA CALI', 'city' => 'Cali'])
        ->assertSessionHasNoErrors();

    $this->put(route('clients.update', $this->client), ['business_name' => 'Cliente', 'nit' => '901674872'])
        ->assertSessionHasNoErrors();

    expect($this->rawMaterial->fresh()->code)->toBe('MP-100')
        ->and($this->product->fresh()->name)->toBe('BP ESMALTE BASE')
        ->and($this->warehouse->fresh()->name)->toBe('PLANTA CALI');
});

test('al editar choca con otro registro que solo cambia en mayúsculas', function (): void {
    $other = Warehouse::factory()->storage()->create(['name' => 'Bodega Neiva']);

    $this->actingAs($this->admin)
        ->putJson(route('warehouses.update', $other), ['name' => 'planta  cali', 'city' => 'Neiva'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

// Respaldo de la regla: un registro que no pasa por un formulario (o dos guardados a la vez) choca con el índice.
test('la base rechaza el nombre que solo cambia en mayúsculas', function (string $table, array $row): void {
    expect(fn () => DB::table($table)->insert([...$row, 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
})->with([
    'bodega' => ['warehouses', ['name' => 'PLANTA CALI', 'city' => 'Cali', 'type' => 'storage']],
    'categoría de producto' => ['product_categories', ['name' => 'esmaltes base']],
    'categoría de MP' => ['raw_material_categories', ['code' => 'OTRA', 'name' => 'RESINAS', 'type' => 'chemical']],
    'tipo de termoencogido' => ['shrink_wrap_types', ['name' => 'galón X4']],
]);

// Un caso por test: en PostgreSQL, después de la primera violación la transacción del test queda abortada.
test('la base rechaza un nombre de producto repetido', function (): void {
    expect(fn () => Product::factory()->create(['name' => 'bp esmalte base']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('la base rechaza un NIT repetido', function (): void {
    expect(fn () => Client::factory()->create(['nit' => '901674872']))
        ->toThrow(UniqueConstraintViolationException::class);
});

test('varios clientes pueden quedar sin NIT', function (): void {
    Client::factory()->count(2)->create(['nit' => null]);

    expect(Client::query()->whereNull('nit')->count())->toBe(2);
});

// El LOWER() de SQLite solo cambia las letras sin tilde; el de PostgreSQL (producción) también las que la llevan.
test('en PostgreSQL, un nombre con tildes en otras mayúsculas también es duplicado', function (): void {
    ProductCategory::factory()->create(['name' => 'Epóxicos']);

    $this->actingAs($this->admin)
        ->postJson(route('catalogs.product-categories.store'), ['name' => 'EPÓXICOS'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');

    expect(fn () => DB::table('product_categories')->insert(['name' => 'EPÓXICOS', 'created_at' => now(), 'updated_at' => now()]))
        ->toThrow(UniqueConstraintViolationException::class);
})->skip(fn () => DB::connection()->getDriverName() !== 'pgsql', 'El LOWER() de SQLite no cambia las letras con tilde.');
