<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Warehouse;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreWarehouseRequest extends FormRequest
{
    use NormalizesText;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Warehouse::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', new UniqueIgnoringCase('warehouses', 'name')],
            'city' => ['bail', 'required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:factory,storage'],
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
