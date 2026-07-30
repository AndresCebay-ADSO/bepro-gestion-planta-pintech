<?php

declare(strict_types=1);

namespace App\Http\Requests\FinishedInventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedProductBatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinishedInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'finished_product_batch_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('finished_product_batches', 'id'),
            ],
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')],
            'type' => [
                'bail',
                Rule::requiredIf(fn () => $this->input('reason') !== FinishedInventoryMovementReason::Transfer->value),
                Rule::in(['entry', 'exit']),
            ],
            'reason' => [
                'bail',
                'required',
                Rule::in(array_column(FinishedInventoryMovementReason::cases(), 'value')),
            ],
            'quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'movement_date' => ['bail', 'required', 'date'],
            'destination_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')],
            'notes' => ['nullable', 'string', 'max:2000'],
            'production_order_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $this->validateReasonForType($validator);
                $this->validateTransferFields($validator);
                $this->validateBatchBelongsToWarehouse($validator);
            },
        ];
    }

    private function validateReasonForType(Validator $validator): void
    {
        $type = InventoryMovementType::tryFrom((string) $this->input('type'));
        $reason = FinishedInventoryMovementReason::tryFrom((string) $this->input('reason'));

        if ($type === null || $reason === null) {
            return;
        }

        $validReasons = FinishedInventoryMovementReason::forType($type);

        if (! in_array($reason, $validReasons, true)) {
            $validator->errors()->add('reason', __('La razón seleccionada no es válida para este tipo de movimiento.'));
        }
    }

    private function validateTransferFields(Validator $validator): void
    {
        $reason = $this->input('reason');

        if ($reason !== FinishedInventoryMovementReason::Transfer->value) {
            return;
        }

        $destinationWarehouseId = $this->input('destination_warehouse_id');

        if ($destinationWarehouseId === null || $destinationWarehouseId === '') {
            $validator->errors()->add('destination_warehouse_id', __('Debe seleccionar la bodega destino para un traslado.'));

            return;
        }

        if ((int) $destinationWarehouseId === (int) $this->input('warehouse_id')) {
            $validator->errors()->add('destination_warehouse_id', __('La bodega destino debe ser diferente a la bodega origen.'));
        }
    }

    private function validateBatchBelongsToWarehouse(Validator $validator): void
    {
        $batchId = $this->input('finished_product_batch_id');
        $type = $this->input('type');

        if ($batchId === null || $type !== 'exit') {
            return;
        }

        $batch = FinishedProductBatch::query()
            ->with(['stocks' => fn ($q) => $q->where('warehouse_id', $this->input('warehouse_id'))->available()])
            ->find((int) $batchId);

        if ($batch === null) {
            return;
        }

        if ($batch->stocks->isEmpty()) {
            $validator->errors()->add('finished_product_batch_id', __('El lote seleccionado no tiene stock disponible en la bodega indicada.'));
        }
    }
}
