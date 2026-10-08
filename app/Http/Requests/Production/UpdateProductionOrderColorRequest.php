<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateProductionOrderColorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('updateColor', $this->route('production_order')) ?? false;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // `present`: una petición sin el campo (o con el nombre mal escrito) no borra el color sin avisar; vacío sí lo borra.
            'color' => ['present', 'nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'color' => 'color',
        ];
    }
}
