<?php

declare(strict_types=1);

use App\Enums\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\RawMaterial;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InventoryService;
use App\Services\RawMaterialReferencePriceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->unit = UnitOfMeasure::create([
        'code' => 'kg',
        'name' => 'Kilogramo',
        'symbol' => 'kg',
    ]);

    $this->warehouse = Warehouse::create([
        'name' => 'Bodega Test Costos',
        'city' => 'Cali',
        'address' => 'Zona Industrial',
        'type' => 'storage',
        'is_active' => true,
    ]);

    $this->rawMaterial = RawMaterial::create([
        'code' => 'RM-POL-001',
        'unit_of_measure_id' => $this->unit->id,
        'current_price' => 3000,
        'previous_price' => null,
        'minimum_stock' => 0,
        'alert_days_before_expiry' => 30,
        'is_active' => true,
    ]);
});

test('conservative policy keeps highest available cost reference when latest lot is cheaper', function () {
    config(['production.raw_material_reference_price_policy' => 'conservative_max']);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 5,
        'remaining_quantity' => 5,
        'unit_price' => 5000,
        'entry_date' => now()->subDays(3)->toDateString(),
        'expiry_date' => null,
        'supplier' => 'Proveedor A',
        'lot_number' => 'LOT-HIGH-01',
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 200,
        'remaining_quantity' => 200,
        'unit_price' => 2000,
        'entry_date' => now()->subDay()->toDateString(),
        'expiry_date' => null,
        'supplier' => 'Proveedor B',
        'lot_number' => 'LOT-LOW-02',
    ]);

    $changed = app(RawMaterialReferencePriceService::class)
        ->syncRawMaterialCurrentPrice((int) $this->rawMaterial->id);

    expect($changed)->toBeTrue();

    $this->rawMaterial->refresh();
    expect((float) $this->rawMaterial->current_price)->toBe(5000.0);
    expect((float) $this->rawMaterial->previous_price)->toBe(3000.0);
});

test('weighted average policy uses weighted stock price as reference', function () {
    config(['production.raw_material_reference_price_policy' => 'weighted_average']);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 1000,
        'entry_date' => now()->subDays(2)->toDateString(),
        'expiry_date' => null,
        'supplier' => 'Proveedor A',
        'lot_number' => 'LOT-W-01',
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 30,
        'remaining_quantity' => 30,
        'unit_price' => 3000,
        'entry_date' => now()->subDay()->toDateString(),
        'expiry_date' => null,
        'supplier' => 'Proveedor B',
        'lot_number' => 'LOT-W-02',
    ]);

    $changed = app(RawMaterialReferencePriceService::class)
        ->syncRawMaterialCurrentPrice((int) $this->rawMaterial->id);

    expect($changed)->toBeTrue();

    $this->rawMaterial->refresh();
    expect((float) $this->rawMaterial->current_price)->toBe(2500.0);
});

test('inventory movements apply the same policy and keep conservative cost reference', function () {
    config(['production.raw_material_reference_price_policy' => 'conservative_max']);

    $user = User::factory()->create();
    $inventoryService = app(InventoryService::class);

    $inventoryService->storeMovement([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_id' => null,
        'production_order_id' => null,
        'type' => InventoryMovementType::Entry->value,
        'quantity' => 10,
        'cost_price' => 5000,
        'movement_date' => now()->toDateString(),
        'notes' => 'Lote alto',
    ], (int) $user->id);

    $inventoryService->storeMovement([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_id' => null,
        'production_order_id' => null,
        'type' => InventoryMovementType::Entry->value,
        'quantity' => 10,
        'cost_price' => 2000,
        'movement_date' => now()->toDateString(),
        'notes' => 'Lote barato',
    ], (int) $user->id);

    $this->rawMaterial->refresh();
    expect((float) $this->rawMaterial->current_price)->toBe(5000.0);
});

test('inventory movement correctly updates current_price for raw materials created without a price', function () {
    $materialWithoutPrice = RawMaterial::factory()->withoutPrice()->create([
        'unit_of_measure_id' => $this->unit->id,
    ]);

    expect($materialWithoutPrice->current_price)->toBeNull();

    $user = User::factory()->create();
    $inventoryService = app(InventoryService::class);

    $inventoryService->storeMovement([
        'raw_material_id' => $materialWithoutPrice->id,
        'warehouse_id' => $this->warehouse->id,
        'batch_id' => null,
        'production_order_id' => null,
        'type' => InventoryMovementType::Entry->value,
        'quantity' => 50,
        'cost_price' => 1500,
        'movement_date' => now()->toDateString(),
        'notes' => 'First entry',
    ], (int) $user->id);

    $materialWithoutPrice->refresh();

    // The price should be updated to 1500 since it's the only lot
    expect((float) $materialWithoutPrice->current_price)->toBe(1500.0);
});

test('reference price sync ignores differences smaller than rounded float precision', function () {
    $this->rawMaterial->update([
        'current_price' => 2500.1234,
        'previous_price' => 2000,
    ]);

    InventoryBatch::create([
        'raw_material_id' => $this->rawMaterial->id,
        'warehouse_id' => $this->warehouse->id,
        'initial_quantity' => 20,
        'remaining_quantity' => 20,
        'unit_price' => 2500.1234000001,
        'entry_date' => now()->toDateString(),
        'expiry_date' => null,
        'supplier' => 'Proveedor Preciso',
        'lot_number' => 'LOT-PREC-01',
    ]);

    $changed = app(RawMaterialReferencePriceService::class)
        ->syncRawMaterialCurrentPrice((int) $this->rawMaterial->id);

    expect($changed)->toBeFalse();

    $this->rawMaterial->refresh();
    expect((float) $this->rawMaterial->current_price)->toBe(2500.1234);
    expect((float) $this->rawMaterial->previous_price)->toBe(2000.0);
});
