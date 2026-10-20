<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\ProductCategory;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;

class StoreProductCategoryRequest extends FormRequest
{
    use NormalizesText;

    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', new UniqueIgnoringCase('product_categories', 'name')],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        return [
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Si la petición no trae el estado: al crear, activa; al editar, el que ya tenía.
     */
    protected function defaultIsActive(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [
            'is_active' => $this->boolean('is_active', $this->defaultIsActive()),
            ...$this->normalizedText(['name' => TextFormat::Name]),
        ];

        if (is_string($this->input('description')) && trim($this->input('description')) === '') {
            $merge['description'] = null;
        }

        $this->merge($merge);
    }
}
