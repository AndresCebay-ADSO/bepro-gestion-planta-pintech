<?php

declare(strict_types=1);

namespace App\Http\Requests\Formulas;

use App\Enums\NumberType;
use App\Enums\RawMaterialType;
use App\Models\Formula;
use App\Rules\RawMaterialOfType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFormulaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $details = $this->input('details', []);

        if (is_array($details)) {
            $this->merge([
                'details' => array_map(function ($detail) {
                    $quantity = is_array($detail) ? ($detail['quantity'] ?? null) : null;

                    // Solo texto o número: convertir un arreglo a texto daba un 500 (FOR-04). Así lo rechaza la regla.
                    if (is_string($quantity) || is_int($quantity) || is_float($quantity)) {
                        $detail['quantity'] = str_replace(',', '.', (string) $quantity);
                    }

                    return $detail;
                }, $details),
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()?->can('create', Formula::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return array_merge(
            [
                'product_id' => [
                    'required',
                    'integer',
                    Rule::exists('products', 'id'),
                ],
                'is_active' => ['boolean'],
                'return_to' => ['nullable', 'string', 'max:2048'],
            ],
            $this->commonRules()
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonRules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'details' => ['required', 'array', 'min:1'],
            'details.*.raw_material_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
                // Solo materias primas de tipo Químico: un envase o una etiqueta no son ingredientes.
                new RawMaterialOfType(RawMaterialType::Chemical),
            ],
            // Por galón, en la unidad de la línea (g, kg, L…): sin tope de negocio, solo el de la columna (FOR-03). Lo que se
            // desborda de verdad es la fórmula × la cantidad de la OP, y eso lo frena CreateProductionOrderAction.
            'details.*.quantity' => ['bail', 'required', ...NumberType::Quantity->rules(), 'min:0.0001'],
            'details.*.unit_of_measure_id' => [
                'required',
                'integer',
                Rule::exists('unit_of_measures', 'id')
                    ->where('is_active', true),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'details.required' => 'La fórmula debe tener al menos un ingrediente.',
            'details.min' => 'La fórmula debe tener al menos un ingrediente.',
            'details.*.raw_material_id.required' => 'Selecciona la materia prima del ingrediente.',
            'details.*.quantity.required' => 'La cantidad del ingrediente es obligatoria.',
            'details.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
            'details.*.unit_of_measure_id.exists' => 'La unidad de medida seleccionada no es válida o no se encuentra activa.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'details' => 'ingredientes',
            'details.*.raw_material_id' => 'materia prima',
            'details.*.quantity' => 'cantidad',
            'details.*.unit_of_measure_id' => 'unidad de medida',
        ];
    }
}
