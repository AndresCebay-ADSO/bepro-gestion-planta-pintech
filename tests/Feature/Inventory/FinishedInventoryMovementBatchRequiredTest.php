<?php

declare(strict_types=1);

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventoryMovement;
use App\Models\Product;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\QueryException;

it('rejects a finished inventory movement without a batch', function (): void {
    FinishedInventoryMovement::create([
        'product_id' => Product::factory()->create()->id,
        'warehouse_id' => Warehouse::factory()->create()->id,
        'type' => InventoryMovementType::Entry,
        'reason' => FinishedInventoryMovementReason::Adjustment,
        'quantity' => 10,
        'movement_date' => '2026-06-01',
        'created_by' => User::factory()->create()->id,
    ]);
})->throws(QueryException::class);
