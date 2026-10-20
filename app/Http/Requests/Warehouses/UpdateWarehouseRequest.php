<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Warehouse;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    use NormalizesText;

    public function authorize(): bool
    {
        $warehouse = $this->route('warehouse');

        return $warehouse instanceof Warehouse
            && ($this->user()?->can('update', $warehouse) ?? false);
    }

    public function rules(): array
    {
        $warehouse = $this->route('warehouse');

        return [
            'name' => [
                'bail',
                'required',
                'string',
                'max:100',
                new UniqueIgnoringCase('warehouses', 'name', $warehouse instanceof Warehouse ? $warehouse->id : null),
            ],
            'city' => ['bail', 'required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            ...$this->normalizedText(['name' => TextFormat::Name]),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }
}
