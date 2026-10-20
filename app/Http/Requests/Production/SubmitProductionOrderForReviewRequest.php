<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\NumberType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SubmitProductionOrderForReviewRequest extends FormRequest
{
    use ProductionConsumptionRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('submitForReview', $this->route('production_order')) ?? false;
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
            $this->seenLinesRules(),
            $this->qualityAndResponsibleRules(),
            [
                // Los mismos tipos que al completar (CompleteProductionOrderRequest).
                'actual_yield_quantity' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'agitation_start_time' => ['nullable', 'date'],
                'agitation_end_time' => ['nullable', 'date'],
                'packaging_start_time' => ['nullable', 'date'],
                'packaging_end_time' => ['nullable', 'date'],
                'spillage_quantity' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'density_kg_per_gallon' => ['bail', 'nullable', ...NumberType::Density->rules(), 'min:0.0001'],
                'remnant_quantity_gallons' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'remnant_notes' => ['nullable', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:2000'],
            ]
        );
    }

    /**
     * Enviar a revisión es entregar la producción para cerrarla: si no se envasó nada, que lo sepa la planta y no quien
     * revisa (P6-1). El rendimiento se exige al completar.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [$this->somethingPackedCheck()];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(
            $this->consumptionAttributes(),
            [
                'actual_yield_quantity' => 'rendimiento real',
                'viscosity_ku' => 'viscosidad (KU)',
                'grinding_hg' => 'molienda (HG)',
                'quality_solids' => 'sólidos de calidad',
                'agitation_start_time' => 'hora de inicio de agitación',
                'agitation_end_time' => 'hora de fin de agitación',
                'packaging_start_time' => 'hora de inicio de envasado',
                'packaging_end_time' => 'hora de fin de envasado',
                'responsible_name' => 'responsable',
                'spillage_quantity' => 'cantidad de merma o reguero',
                'density_kg_per_gallon' => 'densidad (kg/gal)',
                'remnant_quantity_gallons' => 'saldo sobrante (galones)',
                'remnant_notes' => 'observaciones del saldo',
                'notes' => 'observaciones',
            ]
        );
    }
}
