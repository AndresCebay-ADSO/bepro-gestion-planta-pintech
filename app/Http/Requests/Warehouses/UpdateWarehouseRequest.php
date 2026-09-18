<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && ($this->user()?->can('update', $warehouse) ?? false);
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');
        $warehouseId = is_object($warehouse) ? $warehouse->id : $warehouse;

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:100',
                Rule::unique('warehouses', 'name')->ignore($warehouseId),
            ],
            'city' => ['bail', 'required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
