<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\RawMaterialType;
use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\RawMaterialCategory;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRawMaterialCategoryRequest extends FormRequest
{
    use NormalizesText;

    public function authorize(): bool
    {
        return $this->user()?->can('create', RawMaterialCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:50', ...TextFormat::Code->rules(), Rule::unique('raw_material_categories', 'code')],
            'name' => ['bail', 'required', 'string', 'max:100', new UniqueIgnoringCase('raw_material_categories', 'name')],
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
            'type' => ['bail', 'required', Rule::enum(RawMaterialType::class)],
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
        // El código sigue la regla de los códigos de materia prima (D-RM1).
        $merge = [
            'is_active' => $this->boolean('is_active', $this->defaultIsActive()),
            ...$this->normalizedText(['code' => TextFormat::Code, 'name' => TextFormat::Name]),
        ];

        if (is_string($this->input('description')) && trim($this->input('description')) === '') {
            $merge['description'] = null;
        }

        $this->merge($merge);
    }
}
