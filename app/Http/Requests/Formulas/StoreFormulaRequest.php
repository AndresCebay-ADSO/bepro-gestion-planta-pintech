<?php

declare(strict_types=1);

namespace App\Http\Requests\Formulas;

use App\Enums\NumberType;
use App\Enums\RawMaterialType;
use App\Enums\TextFormat;
use App\Http\Requests\Concerns\NormalizesText;
use App\Models\Formula;
use App\Rules\RawMaterialOfType;
use App\Services\FormulaService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreFormulaRequest extends FormRequest
{
    use NormalizesText;

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedText(['name' => TextFormat::Name]));

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
                // Solo productos activos (D-FOR3). CreateFormulaAction lo vuelve a revisar con el producto bloqueado.
                'product_id' => [
                    'required',
                    'integer',
                    Rule::exists('products', 'id')->where('is_active', true),
                ],
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
            // Para distinguir las activas de un producto en el selector de la OP («RAL 7035»). Columna varchar(60).
            'name' => ['nullable', 'string', 'max:60'],
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
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            // Una línea en una unidad que no se convierte a la de su materia prima (kg de un solvente que se compra por
            // litro) no se puede costear ni producir: hoy lo descubría la planta al crear la OP (FOR-02).
            function (Validator $validator): void {
                $details = $this->input('details');

                if (! is_array($details) || $validator->errors()->has('details')) {
                    return;
                }

                // Solo las líneas cuya materia prima y unidad ya pasaron sus reglas (existen, activas y enteras). Aviso
                // temprano: las acciones de crear y editar lo vuelven a revisar con las materias primas bloqueadas.
                $lines = array_filter($details, fn ($detail, $index): bool => ! $validator->errors()->has("details.{$index}.raw_material_id")
                    && ! $validator->errors()->has("details.{$index}.unit_of_measure_id"), ARRAY_FILTER_USE_BOTH);

                foreach (app(FormulaService::class)->unconvertibleLines($lines) as $index => $message) {
                    $validator->errors()->add("details.{$index}.unit_of_measure_id", $message);
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'product_id.exists' => 'Selecciona un producto activo.',
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
            'name' => 'nombre',
            'details' => 'ingredientes',
            'details.*.raw_material_id' => 'materia prima',
            'details.*.quantity' => 'cantidad',
            'details.*.unit_of_measure_id' => 'unidad de medida',
        ];
    }
}
