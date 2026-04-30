<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryMovementRequest extends FormRequest
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
                Rule::exists('raw_materials', 'id')->when(
                    (int) $this->raw_material_id !== (int) ($this->route('inventory_movement')?->raw_material_id),
                    fn ($rule) => $rule->where('is_active', true)
                ),
            ],
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')],
            'batch_id' => ['nullable', 'integer', Rule::exists('inventory_batches', 'id')],
            'production_order_id' => ['nullable', 'integer', Rule::exists('production_orders', 'id')],
            'type' => ['bail', 'required', Rule::in(['entry', 'exit'])],
            'quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4', 'max:99999999.9999'],
            'cost_price' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999'],
            'movement_date' => ['bail', 'required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'created_by' => ['prohibited'],
        ];
    }
}
