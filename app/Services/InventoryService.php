<?php

namespace App\Services;

use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function storeMovement(array $data, int $userId): InventoryMovement
    {
        return DB::transaction(function () use ($data, $userId) {
            $movementData = [
                'raw_material_id' => $data['raw_material_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch_id' => $data['batch_id'] ?? null,
                'production_order_id' => $data['production_order_id'] ?? null,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'cost_price' => $data['cost_price'],
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ];

            $this->applyMovement($movementData['type'], $movementData['quantity'], $movementData['raw_material_id'], $movementData['batch_id']);

            return InventoryMovement::create($movementData);
        });
    }

    public function updateMovement(InventoryMovement $movement, array $data): InventoryMovement
    {
        return DB::transaction(function () use ($movement, $data) {
            $this->reverseMovement($movement);

            $movementData = [
                'raw_material_id' => $data['raw_material_id'],
                'warehouse_id' => $data['warehouse_id'],
                'batch_id' => $data['batch_id'] ?? null,
                'production_order_id' => $data['production_order_id'] ?? null,
                'type' => $data['type'],
                'quantity' => $data['quantity'],
                'cost_price' => $data['cost_price'],
                'movement_date' => $data['movement_date'],
                'notes' => $data['notes'] ?? null,
            ];

            $this->applyMovement($movementData['type'], $movementData['quantity'], $movementData['raw_material_id'], $movementData['batch_id']);

            $movement->update($movementData);

            return $movement->refresh();
        });
    }

    public function deleteMovement(InventoryMovement $movement): void
    {
        DB::transaction(function () use ($movement) {
            $this->reverseMovement($movement);
            $movement->delete();
        });
    }

    private function applyMovement(string $type, string|float $quantity, int $rawMaterialId, ?int $batchId): void
    {
        if ($type === 'exit' && $batchId === null) {
            throw ValidationException::withMessages([
                'batch_id' => __('Debes seleccionar un lote para registrar una salida.'),
            ]);
        }

        if ($batchId === null) {
            return;
        }

        $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($batchId);

        if ((int) $batch->raw_material_id !== $rawMaterialId) {
            throw ValidationException::withMessages([
                'batch_id' => __('El lote seleccionado no pertenece a la materia prima indicada.'),
            ]);
        }

        $quantity = (float) $quantity;

        if ($type === 'entry') {
            $batch->remaining_quantity = (float) $batch->remaining_quantity + $quantity;
            $batch->save();

            return;
        }

        if ((float) $batch->remaining_quantity < $quantity) {
            throw ValidationException::withMessages([
                'quantity' => __('La cantidad supera el stock disponible del lote seleccionado.'),
            ]);
        }

        $batch->remaining_quantity = (float) $batch->remaining_quantity - $quantity;
        $batch->save();
    }

    private function reverseMovement(InventoryMovement $movement): void
    {
        if ($movement->batch_id === null) {
            return;
        }

        $batch = InventoryBatch::query()->lockForUpdate()->findOrFail($movement->batch_id);
        $quantity = (float) $movement->quantity;

        if ($movement->type === 'entry') {
            if ((float) $batch->remaining_quantity < $quantity) {
                throw ValidationException::withMessages([
                    'batch_id' => __('No es posible revertir el movimiento porque el lote no tiene stock suficiente.'),
                ]);
            }

            $batch->remaining_quantity = (float) $batch->remaining_quantity - $quantity;
            $batch->save();

            return;
        }

        $batch->remaining_quantity = (float) $batch->remaining_quantity + $quantity;
        $batch->save();
    }
}
