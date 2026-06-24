<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInventoryBatchRequest extends FormRequest
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
                    (int) $this->raw_material_id !== (int) ($this->route('inventory_batch')?->raw_material_id),
                    fn ($rule) => $rule->where('is_active', true)
                ),
            ],
            'initial_quantity' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4'],
            'remaining_quantity' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4'],
            'unit_price' => ['bail', 'required', 'numeric', 'gt:0', 'decimal:0,4'],
            'entry_date' => ['bail', 'required', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:entry_date'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'lot_number' => ['nullable', 'string', 'max:50'],
        ];
    }
}
