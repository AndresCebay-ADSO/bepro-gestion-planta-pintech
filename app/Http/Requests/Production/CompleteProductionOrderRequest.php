<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ProductionOrderPackagingPlan;
use App\Models\User;
use App\Services\DecimalCalculator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class CompleteProductionOrderRequest extends FormRequest
{
    use ProductionConsumptionRules;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('complete', $this->route('production_order')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $packaging = $this->input('packaging');
        $hasPackaging = is_array($packaging) && $packaging !== [];
        $hasRemnant = (float) ($this->input('remnant_quantity_gallons') ?? 0) > 0;

        return array_merge(
            $this->consumptionRules(),
            [
                'actual_yield_quantity' => [$hasPackaging || $hasRemnant ? 'required' : 'nullable', 'numeric', 'min:0.0001'],
                'viscosity_ku' => ['nullable', 'numeric', 'min:0'],
                'grinding_hg' => ['nullable', 'numeric', 'min:0'],
                'quality_solids' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'agitation_start_time' => ['nullable', 'date'],
                'agitation_end_time' => ['nullable', 'date'],
                'packaging_start_time' => ['nullable', 'date'],
                'packaging_end_time' => ['nullable', 'date'],
                'responsible_name' => ['nullable', 'string', 'max:255'],
                'quality_responsible_user_id' => ['required', 'exists:users,id'],
                'spillage_quantity' => ['nullable', 'numeric', 'min:0'],
                'density_kg_per_gallon' => ['required', 'numeric', 'min:0.0001'],
                'remnant_quantity_gallons' => ['nullable', 'numeric', 'min:0'],
                'remnant_notes' => ['nullable', 'string', 'max:1000'],
                'notes' => ['nullable', 'string'],
            ]
        );
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $calculator = app(DecimalCalculator::class);

                $packaging = $this->input('packaging');
                $hasPackaging = is_array($packaging) && $packaging !== [];
                $remnantGallons = (string) ($this->input('remnant_quantity_gallons') ?? '0');
                $hasRemnant = $calculator->cmp($remnantGallons, '0', 4) > 0;

                if (! $hasPackaging && ! $hasRemnant) {
                    return;
                }

                $actualYield = $this->input('actual_yield_quantity');
                if ($actualYield === null) {
                    return;
                }

                $packagingIds = [];
                if ($hasPackaging) {
                    $packagingIds = collect($packaging)
                        ->pluck('id')
                        ->filter()
                        ->map(fn ($id) => (int) $id)
                        ->values()
                        ->all();
                }

                $plans = collect();
                if ($packagingIds !== []) {
                    $plans = ProductionOrderPackagingPlan::query()
                        ->whereIn('id', $packagingIds)
                        ->with('productVariant:id,presentation_value')
                        ->get()
                        ->keyBy('id');
                }

                $expectedYield = '0';

                if ($hasPackaging) {
                    foreach ($packaging as $packagingData) {
                        $planId = (int) ($packagingData['id'] ?? 0);
                        $plan = $plans->get($planId);
                        if ($plan === null) {
                            continue;
                        }

                        $actualUnits = (string) ($packagingData['actual_units'] ?? '0');
                        if ($calculator->cmp($actualUnits, '0', 4) <= 0) {
                            continue;
                        }

                        $presentationValue = (string) ($plan->productVariant?->presentation_value ?? '1');
                        $yieldAddition = $calculator->mul($actualUnits, $presentationValue, 4);
                        $expectedYield = $calculator->add($expectedYield, $yieldAddition, 4);
                    }
                }

                if ($hasRemnant) {
                    $expectedYield = $calculator->add($expectedYield, $remnantGallons, 4);
                }

                $actualYieldStr = (string) $actualYield;
                $difference = $calculator->abs($calculator->sub($actualYieldStr, $expectedYield, 4), 4);
                $yieldTolerance = (string) config('production.yield_tolerance', '0.01');

                if ($calculator->cmp($difference, $yieldTolerance, 4) <= 0) {
                    return;
                }

                $validator->errors()->add(
                    'actual_yield_quantity',
                    "El rendimiento real debe coincidir con el envasado equivalente más el saldo. Registrado: {$actualYield}, esperado: {$expectedYield} (tolerancia: {$yieldTolerance})."
                );
            },
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $signerId = $this->input('quality_responsible_user_id');

                if ($signerId === null) {
                    return;
                }

                $user = User::query()->find($signerId);

                if ($user === null) {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado no existe.');

                    return;
                }

                if (! $user->is_active) {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado no está activo.');

                    return;
                }

                if (! $user->hasAnyRole(['admin', 'produccion'])) {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado debe tener rol administrador o producción.');
                }

                if ($user->job_title === null || $user->job_title === '') {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado no tiene un cargo configurado.');
                }

                if ($user->signature_path === null || $user->signature_path === '') {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado no tiene una firma cargada.');
                }
            },
        ];
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
                'quality_responsible_user_id' => 'responsable de calidad',
                'spillage_quantity' => 'cantidad de merma o reguero',
                'density_kg_per_gallon' => 'densidad (kg/gal)',
                'remnant_quantity_gallons' => 'saldo sobrante (galones)',
                'remnant_notes' => 'observaciones del saldo',
                'notes' => 'observaciones',
            ]
        );
    }
}
