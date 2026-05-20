<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Models\ProductionOrder;

class InventoryMovementService
{
    public function recordProductionRawMaterialConsumption(
        ProductionOrder $order,
        int $rawMaterialId,
        ?int $batchId,
        float $quantity,
        float $unitPrice,
        int $userId,
        string $notes
    ): InventoryMovement {
        return InventoryMovement::create([
            'raw_material_id' => $rawMaterialId,
            'warehouse_id' => $order->warehouse_id,
            'batch_id' => $batchId,
            'production_order_id' => $order->id,
            'type' => InventoryMovementType::Exit,
            'quantity' => $quantity,
            'cost_price' => $unitPrice,
            'movement_date' => now(),
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }
}
