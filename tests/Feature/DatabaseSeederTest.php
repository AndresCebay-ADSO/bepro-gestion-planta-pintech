<?php

declare(strict_types=1);

use App\Models\Formula;
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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Ningún otro test ejecuta los seeders de datos: los fallos que solo aparecen al sembrar (como un TypeError por
// strict_types) llegaban hasta `migrate:fresh --seed` sin que el CI los viera.
test('la siembra completa de la base termina y llena cada catálogo', function () {
    $this->seed(DatabaseSeeder::class);

    foreach ([
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
        SalesOrder::class,
    ] as $model) {
        expect($model::query()->exists())->toBeTrue("{$model} quedó vacío tras la siembra");
    }
});
