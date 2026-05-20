<?php

declare(strict_types=1);

use App\Models\Formula;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductionOrder;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\Inventory\FifoStockAllocatorService;
use Carbon\CarbonInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();

    $this->unitOfMeasure = UnitOfMeasure::create([
        'code' => 'L-FIFO',
        'name' => 'Litro FIFO',
        'symbol' => 'L',
    ]);

    $category = ProductCategory::create(['name' => 'Pinturas FIFO']);

    $this->product = Product::create([
        'code' => 'P-FIFO',
        'name' => 'Producto FIFO',
        'category_id' => $category->id,
        'unit_of_measure_id' => $this->unitOfMeasure->id,
        'current_cost' => 10,
        'profit_margin' => 20,
        'current_price' => 12,
        'price_threshold' => 5,
    ]);

    $this->formula = Formula::create([
        'product_id' => $this->product->id,
        'version' => 1,
        'is_active' => true,
        'created_by' => $this->user->id,
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Planta FIFO',
        'city' => 'Cali',
        'type' => 'factory',
        'is_active' => true,
    ]);

    $this->order = ProductionOrder::create([
        'order_number' => 'OP-FIFO-SVC',
        'product_id' => $this->product->id,
        'formula_id' => $this->formula->id,
        'warehouse_id' => $this->warehouse->id,
        'quantity' => 100,
        'status' => 'pending',
        'planned_date' => now(),
        'created_by' => $this->user->id,
    ]);
});

test('it consumes tracked raw material across fifo batches', function () {
    $rawMaterial = createFifoRawMaterial($this);
    $oldestBatch = createFifoBatch($rawMaterial, $this->warehouse, 50, 4, now()->subDays(2));
    $newestBatch = createFifoBatch($rawMaterial, $this->warehouse, 50, 6, now()->subDay());

    $cost = app(FifoStockAllocatorService::class)->consumeRawMaterialForProduction(
        order: $this->order,
        rawMaterialId: $rawMaterial->id,
        requiredQuantity: 75,
        userId: $this->user->id,
        errorKey: 'ingredients'
    );

    expect($cost)->toBe(350.0)
        ->and((float) $oldestBatch->refresh()->remaining_quantity)->toBe(0.0)
        ->and((float) $newestBatch->refresh()->remaining_quantity)->toBe(25.0);

    expect(InventoryMovement::query()->where('raw_material_id', $rawMaterial->id)->count())->toBe(2);
});

test('it consumes untracked raw material without requiring batches', function () {
    $rawMaterial = createFifoRawMaterial($this, [
        'code' => 'RM-FIFO-NOTRACK',
        'current_price' => 8,
        'tracks_inventory' => false,
    ]);

    $cost = app(FifoStockAllocatorService::class)->consumeRawMaterialForProduction(
        order: $this->order,
        rawMaterialId: $rawMaterial->id,
        requiredQuantity: 12.5,
        userId: $this->user->id,
        errorKey: 'ingredients'
    );

    expect($cost)->toBe(100.0);

    $movement = InventoryMovement::query()->where('raw_material_id', $rawMaterial->id)->sole();

    expect($movement->batch_id)->toBeNull()
        ->and((float) $movement->quantity)->toBe(12.5)
        ->and((float) $movement->cost_price)->toBe(8.0);
});

test('it rejects fifo consumption when tracked stock is insufficient', function () {
    $rawMaterial = createFifoRawMaterial($this, ['code' => 'RM-FIFO-SHORT']);
    createFifoBatch($rawMaterial, $this->warehouse, 10, 5, now()->subDay());

    expect(fn () => app(FifoStockAllocatorService::class)->consumeRawMaterialForProduction(
        order: $this->order,
        rawMaterialId: $rawMaterial->id,
        requiredQuantity: 15,
        userId: $this->user->id,
        errorKey: 'ingredients'
    ))->toThrow(ValidationException::class);
});

test('it estimates weighted average unit cost from available fifo batches', function () {
    $rawMaterial = createFifoRawMaterial($this);
    createFifoBatch($rawMaterial, $this->warehouse, 50, 4, now()->subDays(2));
    createFifoBatch($rawMaterial, $this->warehouse, 50, 6, now()->subDay());

    $cost = app(FifoStockAllocatorService::class)->estimateMaterialUnitCostForPlanning(
        rawMaterialId: $rawMaterial->id,
        warehouseId: $this->warehouse->id,
        requiredQuantity: 75
    );

    expect($cost)->toBe(4.666666666666667);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function createFifoRawMaterial(object $context, array $overrides = []): RawMaterial
{
    return RawMaterial::create(array_merge([
        'code' => 'RM-FIFO',
        'unit_of_measure_id' => $context->unitOfMeasure->id,
        'current_price' => 5,
        'tracks_inventory' => true,
        'is_active' => true,
    ], $overrides));
}

function createFifoBatch(
    RawMaterial $rawMaterial,
    Warehouse $warehouse,
    float $quantity,
    float $unitPrice,
    CarbonInterface $entryDate
): InventoryBatch {
    return InventoryBatch::create([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'initial_quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'unit_price' => $unitPrice,
        'entry_date' => $entryDate,
    ]);
}
