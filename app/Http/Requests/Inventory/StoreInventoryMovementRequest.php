<?php

namespace App\Http\Requests\Inventory;

use App\Models\InventoryBatch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'produccion']) ?? false;
    }

    public function rules(): array
    {
        return [
            'raw_material_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
            ],
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')],
            'batch_id' => ['nullable', 'integer', Rule::exists('inventory_batches', 'id')],
            'production_order_id' => ['nullable', 'integer', Rule::exists('production_orders', 'id')],
            'type' => ['bail', 'required', Rule::in(['entry', 'exit'])],
            'quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'cost_price' => [
                'bail',
                Rule::requiredIf(fn () => $this->input('type') === 'entry'),
                'nullable',
                'numeric',
                'min:0',
                'decimal:0,4',
                'max:99999999.9999',
            ],
            'movement_date' => ['bail', 'required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'created_by' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $batchId = $this->input('batch_id');
                if ($batchId === null || $batchId === '') {
                    return;
                }

                $batch = InventoryBatch::query()->find((int) $batchId);
                if ($batch === null) {
                    return;
                }

                if ((int) $batch->raw_material_id !== (int) $this->input('raw_material_id')) {
                    $validator->errors()->add('batch_id', __('El lote seleccionado no pertenece a la materia prima indicada.'));
                }

                if ((int) $batch->warehouse_id !== (int) $this->input('warehouse_id')) {
                    $validator->errors()->add('batch_id', __('El lote seleccionado no pertenece a la bodega indicada.'));
                }
            },
        ];
    }
}
