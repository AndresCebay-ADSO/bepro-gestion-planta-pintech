<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryBatchRequest extends FormRequest
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
            'warehouse_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
            ],
            'initial_quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4'],
            'remaining_quantity' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'unit_price' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4'],
            'entry_date' => ['bail', 'required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'lot_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
