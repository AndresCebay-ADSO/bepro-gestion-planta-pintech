<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use App\Services\TimezoneService;

class InventoryMovementService
{
    public function __construct(
        private readonly TimezoneService $timezone,
    ) {}

    /**
     * Registra la salida de una materia prima consumida, enlazada a su origen: una OP (`production_order_id`) o un
     * termoencogido (`shrink_wrap_id`). Sin fecha, la de hoy en la planta: `movement_date` es una fecha y en UTC una
     * salida de las 8 p. m. en Bogotá quedaría en el día siguiente (B54).
     *
     * @param  array{production_order_id?: int, shrink_wrap_id?: int}  $origin
     */
    public function recordConsumption(
        int $rawMaterialId,
        int $warehouseId,
        ?int $batchId,
        float|string $quantity,
        float|string $unitPrice,
        int $userId,
        string $notes,
        array $origin,
        ?string $movementDate = null,
    ): InventoryMovement {
        return InventoryMovement::create([
            'raw_material_id' => $rawMaterialId,
            'warehouse_id' => $warehouseId,
            'batch_id' => $batchId,
            'production_order_id' => $origin['production_order_id'] ?? null,
            'shrink_wrap_id' => $origin['shrink_wrap_id'] ?? null,
            'type' => InventoryMovementType::Exit,
            'quantity' => $quantity,
            'cost_price' => $unitPrice,
            'movement_date' => $movementDate ?? $this->timezone->todayInPlant(),
            'notes' => $notes,
            'created_by' => $userId,
        ]);
    }
}
