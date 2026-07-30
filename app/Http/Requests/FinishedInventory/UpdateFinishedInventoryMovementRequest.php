<?php

declare(strict_types=1);

namespace App\Http\Requests\FinishedInventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedProductBatch;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateFinishedInventoryMovementRequest extends FormRequest
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
            'type' => ['bail', 'required', Rule::in(['entry', 'exit'])],
            'reason' => [
                'bail',
                'required',
                Rule::in(array_column(FinishedInventoryMovementReason::cases(), 'value')),
            ],
            'quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'movement_date' => ['bail', 'required', 'date'],
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
                $type = InventoryMovementType::tryFrom((string) $this->input('type'));
                $reason = FinishedInventoryMovementReason::tryFrom((string) $this->input('reason'));

                if ($type === null || $reason === null) {
                    return;
                }

                $validReasons = FinishedInventoryMovementReason::forType($type);

                if (! in_array($reason, $validReasons, true)) {
                    $validator->errors()->add('reason', __('La razón seleccionada no es válida para este tipo de movimiento.'));
                }

                if ($reason === FinishedInventoryMovementReason::Transfer) {
                    $validator->errors()->add('reason', __('No se puede cambiar un movimiento existente a Traslado. Los traslados se crean como pares desde cero.'));
                }

                $this->validateBatchBelongsToWarehouse($validator);
            },
        ];
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
