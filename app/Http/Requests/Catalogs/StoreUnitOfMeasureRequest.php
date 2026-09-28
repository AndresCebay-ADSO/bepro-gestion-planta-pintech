<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', UnitOfMeasure::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'code' => ['bail', 'required', 'string', 'max:20', Rule::unique('unit_of_measures', 'code')],
            ...$this->sharedRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_kg_conversion.prohibits' => __('Una unidad se convierte por peso o por volumen, no por ambos: deja solo una equivalencia.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        // Un solo factor: getConversionFactor() prefiere el de volumen, así que con los dos el de peso se ignoraría.
        // El tope es el de la columna, decimal(10,4).
        $factor = ['nullable', 'numeric', 'gt:0', 'decimal:0,4', 'max:999999.9999'];

        return [
            'name' => ['bail', 'required', 'string', 'max:100'],
            'symbol' => ['bail', 'required', 'string', 'max:10'],
            'description' => ['nullable', 'string', 'max:1000'],
            'to_kg_conversion' => [...$factor, 'prohibits:to_liter_conversion'],
            'to_liter_conversion' => $factor,
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Si la petición no trae el estado: al crear, activa; al editar, la que ya tenía.
     */
    protected function defaultIsActive(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = ['is_active' => $this->boolean('is_active', $this->defaultIsActive())];

        // El código es un identificador: sin espacios ni variantes de mayúsculas («KG» y «kg» serían dos unidades).
        if (is_string($this->input('code'))) {
            $merge['code'] = Str::lower(trim($this->input('code')));
        }

        foreach (['to_kg_conversion', 'to_liter_conversion', 'description'] as $key) {
            // Solo texto vacío pasa a null; cualquier otro tipo (p. ej. una lista) llega tal cual y lo rechaza la validación.
            if (is_string($this->input($key)) && trim($this->input($key)) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }
}
