<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\RawMaterialType;
use App\Models\RawMaterialCategory;
use App\Rules\UniqueIgnoringCase;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRawMaterialCategoryRequest extends FormRequest
{
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
            'code' => ['bail', 'required', 'string', 'max:50', Rule::unique('raw_material_categories', 'code')],
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
        $merge = ['is_active' => $this->boolean('is_active', $this->defaultIsActive())];

        // El código es un identificador: sin espacios y en mayúsculas, como los de las materias primas.
        if (is_string($this->input('code'))) {
            $merge['code'] = Str::upper(trim($this->input('code')));
        }

        if (is_string($this->input('description')) && trim($this->input('description')) === '') {
            $merge['description'] = null;
        }

        $this->merge($merge);
    }
}
