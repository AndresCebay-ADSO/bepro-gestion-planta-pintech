<?php

declare(strict_types=1);

use App\Enums\InventoryMovementType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('raw material factory creates valid related records', function () {
    $rawMaterial = RawMaterial::factory()->create();

    $this->assertDatabaseHas('raw_materials', [
        'id' => $rawMaterial->id,
        'code' => $rawMaterial->code,
    ]);

    expect($rawMaterial->category)->not->toBeNull();
    expect($rawMaterial->unitOfMeasure)->not->toBeNull();
});

test('inventory batch factory creates a stocked batch with relationships', function () {
    $batch = InventoryBatch::factory()->create();

    expect($batch->rawMaterial)->not->toBeNull();
    expect($batch->warehouse)->not->toBeNull();
    expect((float) $batch->remaining_quantity)->toBe((float) $batch->initial_quantity);
});

test('inventory movement factory creates a valid inventory movement', function () {
    $movement = InventoryMovement::factory()->entry()->create();

    expect($movement->rawMaterial)->not->toBeNull();
    expect($movement->warehouse)->not->toBeNull();
    expect($movement->batch)->not->toBeNull();
    expect($movement->createdBy)->not->toBeNull();
    expect($movement->type)->toBe(InventoryMovementType::Entry);
});
