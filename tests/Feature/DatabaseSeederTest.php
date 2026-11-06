<?php

declare(strict_types=1);

use App\Enums\RawMaterialType;
use App\Enums\SystemRole;
use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionCost;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FormulaSeeder;
use Database\Seeders\InventoryBatchSeeder;
use Database\Seeders\ProductCategorySeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\RawMaterialCategorySeeder;
use Database\Seeders\RawMaterialSeeder;
use Database\Seeders\SalesOrderSeeder;
use Database\Seeders\UnitsOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Finder\Finder;

uses(RefreshDatabase::class);

/** @return list<class-string<Model>> */
function seededModels(): array
{
    return [
        UnitOfMeasure::class,
        RawMaterialCategory::class,
        RawMaterial::class,
        ProductCategory::class,
        Warehouse::class,
        User::class,
        Product::class,
        InventoryBatch::class,
        ProductVariant::class,
        Formula::class,
        FormulaDetail::class,
        SalesOrder::class,
    ];
}

// Ningún otro test ejecuta los seeders de datos: los fallos que solo aparecen al sembrar (como un TypeError por
// strict_types) llegaban hasta `migrate:fresh --seed` sin que el CI los viera.
test('la siembra completa de la base termina y llena cada catálogo', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (seededModels() as $model) {
        expect($model::query()->exists())->toBeTrue("{$model} quedó vacío tras la siembra");
    }
});

// La siembra corre sin eventos de modelo (WithoutModelEvents), así que la regla de Formula que deja de referencia la
// primera fórmula activa no actúa: FormulaSeeder la marca a mano. Sin referencia el producto no se costea y no avisa.
test('la siembra deja cada producto con fórmula con una sola de referencia, ya costeada', function () {
    $this->seed(DatabaseSeeder::class);

    $productIds = Formula::query()->distinct()->pluck('product_id');

    foreach ($productIds as $productId) {
        expect(Formula::query()->where('product_id', $productId)->priceReference()->count())
            ->toBe(1, "El producto {$productId} quedó sin fórmula de referencia")
            ->and(ProductionCost::query()->where('product_id', $productId)->whereNull('production_order_id')->exists())
            ->toBeTrue("El producto {$productId} no se costeó");
    }
});

test('sembrar dos veces no duplica registros', function () {
    $this->seed(DatabaseSeeder::class);
    $counts = collect(seededModels())->mapWithKeys(fn (string $model) => [$model => $model::query()->count()]);

    $this->seed(DatabaseSeeder::class);

    foreach ($counts as $model => $count) {
        expect($model::query()->count())->toBe($count, "{$model} cambió al sembrar de nuevo");
    }
});

// Los catálogos se editan desde la aplicación: volver a sembrar crea lo que falte, pero nunca deshace esas ediciones
// (firstOrCreate, no updateOrCreate). Antes restablecía equivalencias, tipos, precios y hasta las líneas de las fórmulas.
test('volver a sembrar no deshace lo editado desde la aplicación', function () {
    $this->seed(DatabaseSeeder::class);

    $unit = UnitOfMeasure::query()->where('code', 'gl')->sole();
    $unit->update(['name' => 'Galón USA', 'to_liter_conversion' => '3.7900']);

    $category = RawMaterialCategory::query()->where('code', 'ETIQUETAS')->sole();
    $category->update(['name' => 'Rótulos', 'type' => RawMaterialType::SecondaryPackaging, 'is_active' => false]);

    $material = RawMaterial::query()->where('code', 'ENV-P-GL')->sole();
    $material->update(['current_price' => '1234.5', 'minimum_stock' => '7', 'is_active' => false]);

    $warehouse = Warehouse::query()->where('name', 'Bodega Neiva')->sole();
    $warehouse->update(['address' => 'Nueva sede Neiva']);

    $productCategory = ProductCategory::query()->orderBy('id')->firstOrFail();
    $productCategory->update(['description' => 'Descripción propia']);

    $product = Product::query()->orderBy('id')->firstOrFail();
    $product->update(['cif_percentage' => '22', 'is_active' => false]);

    $formula = Formula::query()->has('details', '>', 1)->orderBy('id')->firstOrFail();
    $formula->details()->orderByDesc('step_order')->firstOrFail()->delete();
    $formula->update(['notes' => 'Ajustada en planta']);
    $lines = $formula->details()->count();

    $this->seed(DatabaseSeeder::class);

    expect($unit->fresh())
        ->name->toBe('Galón USA')
        ->to_liter_conversion->toBe('3.790000')
        ->and($category->fresh())
        ->name->toBe('Rótulos')
        ->type->toBe(RawMaterialType::SecondaryPackaging)
        ->is_active->toBeFalse()
        ->and($material->fresh())
        ->current_price->toBe('1234.5000')
        ->minimum_stock->toBe('7.0000')
        ->is_active->toBeFalse()
        ->and($warehouse->fresh()->address)->toBe('Nueva sede Neiva')
        ->and($productCategory->fresh()->description)->toBe('Descripción propia')
        ->and($product->fresh())
        ->cif_percentage->toBe('22.00')
        ->is_active->toBeFalse()
        ->and($formula->fresh()->notes)->toBe('Ajustada en planta')
        ->and($formula->details()->count())->toBe($lines);
});

test('volver a sembrar no choca con una categoría a la que le cambiaron el código o las mayúsculas', function () {
    $this->seed(DatabaseSeeder::class);
    RawMaterialCategory::query()->where('code', 'ETIQUETAS')->sole()->update(['code' => 'ETIQ']);
    ProductCategory::query()->where('name', 'Masillas y Empastes')->sole()->update(['name' => 'MASILLAS Y EMPASTES']);
    $counts = [RawMaterialCategory::query()->count(), ProductCategory::query()->count()];

    $this->seed(DatabaseSeeder::class);

    expect([RawMaterialCategory::query()->count(), ProductCategory::query()->count()])->toBe($counts)
        ->and(RawMaterialCategory::query()->where('name', 'Etiquetas')->sole()->code)->toBe('ETIQ');
});

test('volver a sembrar completa una fórmula que quedó sin líneas', function () {
    $this->seed(DatabaseSeeder::class);
    $formula = Formula::query()->has('details')->orderBy('id')->firstOrFail();
    $lines = $formula->details()->count();

    // Simula una siembra cortada a mitad: la cabecera quedó sin líneas.
    $formula->details()->delete();

    $this->seed(FormulaSeeder::class);

    expect($formula->details()->count())->toBe($lines);
});

test('volver a sembrar completa los lotes de un material que quedó sin ellos', function () {
    $this->seed([UnitsOfMeasureSeeder::class, RawMaterialCategorySeeder::class, RawMaterialSeeder::class, WarehouseSeeder::class]);
    $this->seed(InventoryBatchSeeder::class);
    $total = InventoryBatch::query()->count();
    $materialId = InventoryBatch::query()->value('raw_material_id');
    $materialBatches = InventoryBatch::query()->where('raw_material_id', $materialId)->count();

    // Simula una siembra cortada a mitad: un material se quedó sin lotes.
    InventoryBatch::query()->where('raw_material_id', $materialId)->delete();

    $this->seed(InventoryBatchSeeder::class);

    expect(InventoryBatch::query()->where('raw_material_id', $materialId)->count())->toBe($materialBatches)
        ->and(InventoryBatch::query()->count())->toBe($total);
});

test('los lotes de prueba no se siembran fuera de desarrollo y pruebas', function () {
    $this->seed([UnitsOfMeasureSeeder::class, RawMaterialCategorySeeder::class, RawMaterialSeeder::class, WarehouseSeeder::class]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => InventoryBatchSeeder::class, '--force' => true])->assertSuccessful();

    expect(InventoryBatch::query()->exists())->toBeFalse();
});

// Staging (2026-11): los datos de demostración se siembran una vez, a mano, pero los usuarios no. El primero lo crea
// `users:create-admin` antes de sembrar, y figura como creador de pedidos y fórmulas.
test('en staging siembra los datos de demostración sin sembrar usuarios', function () {
    $admin = userWithRole(SystemRole::SuperAdmin);
    app()->detectEnvironment(fn () => 'staging');

    $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

    expect(User::query()->count())->toBe(1)
        ->and(User::query()->where('email', 'soporte@pintech.test')->exists())->toBeFalse()
        ->and(InventoryBatch::query()->exists())->toBeTrue()
        ->and(ProductVariant::query()->exists())->toBeTrue()
        ->and(FormulaDetail::query()->exists())->toBeTrue()
        ->and(SalesOrder::query()->exists())->toBeTrue()
        ->and(SalesOrder::query()->where('created_by', '!=', $admin->id)->exists())->toBeFalse();
});

test('los pedidos de demostración no se siembran en producción', function () {
    userWithRole(SystemRole::SuperAdmin);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => SalesOrderSeeder::class, '--force' => true])->assertSuccessful();

    expect(SalesOrder::query()->exists())->toBeFalse();
});

// La imagen del servidor se compila sin dependencias de desarrollo: Faker no está, y las factories lo usan. Un seeder
// que los use funciona aquí y falla en staging («Class "Faker\Factory" not found»).
test('ningún seeder usa Faker ni factories', function () {
    $offenders = collect(Finder::create()->files()->in(database_path('seeders'))->name('*.php'))
        ->filter(fn (SplFileInfo $file): bool => preg_match('/^use Faker\\\\|\bfake\(|::factory\(/m', (string) file_get_contents($file->getPathname())) === 1)
        ->map(fn (SplFileInfo $file): string => $file->getFilename())
        ->values()
        ->all();

    expect($offenders)->toBe([]);
});

test('cada línea de las fórmulas de demostración encuentra su materia prima', function () {
    $this->seed([UnitsOfMeasureSeeder::class, RawMaterialCategorySeeder::class, RawMaterialSeeder::class]);
    $this->seed([ProductCategorySeeder::class, ProductSeeder::class]);
    User::factory()->create();

    $this->artisan('db:seed', ['--class' => FormulaSeeder::class])
        ->doesntExpectOutputToContain('no encontrad')
        ->assertSuccessful();
});
