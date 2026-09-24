<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\FormulaDetail;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\RawMaterial;
use App\Models\RawMaterialCategory;
use App\Models\SalesOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\InventoryBatchSeeder;
use Database\Seeders\RawMaterialCategorySeeder;
use Database\Seeders\RawMaterialSeeder;
use Database\Seeders\UnitsOfMeasureSeeder;
use Database\Seeders\WarehouseSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

test('sembrar dos veces no duplica registros', function () {
    $this->seed(DatabaseSeeder::class);
    $counts = collect(seededModels())->mapWithKeys(fn (string $model) => [$model => $model::query()->count()]);

    $this->seed(DatabaseSeeder::class);

    foreach ($counts as $model => $count) {
        expect($model::query()->count())->toBe($count, "{$model} cambió al sembrar de nuevo");
    }
});

test('los lotes de prueba no se siembran fuera de desarrollo y pruebas', function () {
    $this->seed([UnitsOfMeasureSeeder::class, RawMaterialCategorySeeder::class, RawMaterialSeeder::class, WarehouseSeeder::class]);
    app()->detectEnvironment(fn () => 'production');

    $this->artisan('db:seed', ['--class' => InventoryBatchSeeder::class, '--force' => true])->assertSuccessful();

    expect(InventoryBatch::query()->exists())->toBeFalse();
});
