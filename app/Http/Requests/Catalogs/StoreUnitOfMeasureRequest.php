<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\NumberType;
use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\UnitOfMeasure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUnitOfMeasureRequest extends FormRequest
{
    use NormalizesText;

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
        // Un solo factor: FormulaService::convertQuantity() prefiere el de volumen, así que con los dos el de peso se ignoraría.
        // Seis decimales y el tope de su columna (NumberType::Factor): un mililitro son 0,001 L.
        $factor = ['nullable', ...NumberType::Factor->rules(), 'gt:0'];

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
        $merge = [
            'is_active' => $this->boolean('is_active', $this->defaultIsActive()),
            ...$this->normalizedText(['code' => TextFormat::UnitCode, 'name' => TextFormat::Name]),
        ];

        foreach (['to_kg_conversion', 'to_liter_conversion', 'description'] as $key) {
            // Solo texto vacío pasa a null; cualquier otro tipo (p. ej. una lista) llega tal cual y lo rechaza la validación.
            if (is_string($this->input($key)) && trim($this->input($key)) === '') {
                $merge[$key] = null;
            }
        }

        $this->merge($merge);
    }
}
