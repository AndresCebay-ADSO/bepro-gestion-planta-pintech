<?php

namespace App\Http\Requests\Warehouses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('admin') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', Rule::unique('warehouses', 'name')->whereNull('deleted_at')],
            'city' => ['bail', 'required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:factory,storage'],
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
