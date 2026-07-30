<?php

use App\Exceptions\InsufficientStockException;
use App\Models\FinishedProductBatch;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\UnitOfMeasure;
use App\Models\Warehouse;
use App\Services\DecimalCalculator;
use App\Services\FinishedInventory\FinishedProductBatchStockService;

beforeEach(function () {
    $this->service = new FinishedProductBatchStockService(new DecimalCalculator);
});

it('throws exception when decrementing stock that does not exist', function () {
    $warehouse = Warehouse::factory()->create();
    $unit = UnitOfMeasure::factory()->create();
    $category = ProductCategory::create(['name' => 'Cat Test']);

    $product = Product::create([
        'code' => 'PROD-TEST',
        'name' => 'Producto Test',
        'brand' => 'BEPRO',
        'unit_of_measure_id' => $unit->id,
        'category_id' => $category->id,
        'is_active' => true,
    ]);

    $batch = FinishedProductBatch::create([
        'product_id' => $product->id,
        'initial_quantity' => '100',
        'entry_date' => now(),
    ]);

    // No stock record exists for this batch in this warehouse yet
    expect(fn () => $this->service->decrementStock($batch->id, $warehouse->id, '10'))
        ->toThrow(InsufficientStockException::class);
});
