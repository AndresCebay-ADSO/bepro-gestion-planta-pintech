<?php

declare(strict_types=1);

use App\Http\Requests\Inventory\StoreInventoryBatchRequest;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

test('store inventory batch request requires an active warehouse', function () {
    $rawMaterial = RawMaterial::factory()->create();
    $inactiveWarehouse = Warehouse::factory()->inactive()->create();
    $request = new StoreInventoryBatchRequest;

    $validator = Validator::make([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $inactiveWarehouse->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 50,
        'entry_date' => now()->toDateString(),
    ], $request->rules());

    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('warehouse_id'))->toBeTrue();
});

test('store inventory batch request passes with an active warehouse', function () {
    $rawMaterial = RawMaterial::factory()->create();
    $warehouse = Warehouse::factory()->create();
    $request = new StoreInventoryBatchRequest;

    $validator = Validator::make([
        'raw_material_id' => $rawMaterial->id,
        'warehouse_id' => $warehouse->id,
        'initial_quantity' => 10,
        'remaining_quantity' => 10,
        'unit_price' => 50,
        'entry_date' => now()->toDateString(),
    ], $request->rules());

    expect($validator->fails())->toBeFalse();
});
