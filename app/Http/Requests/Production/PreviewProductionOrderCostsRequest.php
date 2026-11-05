<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\NumberType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreviewProductionOrderCostsRequest extends FormRequest
{
    use ProductionConsumptionRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('previewCosts', $this->route('production_order')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(
            $this->consumptionRules(),
            [
                'remnant_quantity_kg' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                // Opcional aquí: sin él, la vista previa calcula sin sobrante y no da error.
                'density_kg_per_gallon' => ['bail', 'nullable', ...NumberType::Density->rules(), 'min:0.0001'],
            ]
        );
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(
            $this->consumptionAttributes(),
            [
                'remnant_quantity_kg' => 'sobrante pesado (kg)',
                'density_kg_per_gallon' => 'peso por galón (kg/gal)',
            ]
        );
    }
}
