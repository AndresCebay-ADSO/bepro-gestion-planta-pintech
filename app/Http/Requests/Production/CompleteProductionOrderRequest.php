<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\NumberType;
use App\Enums\Permission;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\User;
use App\Services\DecimalCalculator;
use App\Services\Production\ProductionOrderCompletionLines;
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
        return array_merge(
            $this->consumptionRules(),
            $this->seenLinesRules(),
            $this->qualityAndResponsibleRules(),
            [
                // Cada cantidad con el tipo de su columna (Cantidad 12,4 y Densidad 10,4): sin tope, 1e65 o 1e20 pasan
                // `numeric` y fallan en la calculadora o al guardar en PostgreSQL. Siempre obligatorio: una orden solo se
                // completa si algo se envasó o quedó como saldo (D-OPL1), y eso es lo que rinde.
                'actual_yield_quantity' => ['bail', 'required', ...NumberType::Quantity->rules(), 'min:0.0001'],
                'agitation_start_time' => ['nullable', 'date'],
                'agitation_end_time' => ['nullable', 'date'],
                'packaging_start_time' => ['nullable', 'date'],
                'packaging_end_time' => ['nullable', 'date'],
                'quality_responsible_user_id' => ['bail', 'required', 'integer', 'exists:users,id'],
                'spillage_quantity' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'density_kg_per_gallon' => ['bail', 'required', ...NumberType::Density->rules(), 'min:0.0001'],
                'remnant_quantity_gallons' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'remnant_notes' => ['nullable', 'string', 'max:1000'],
                'notes' => ['nullable', 'string', 'max:2000'],
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
            // El porcentaje de rendimiento se guarda en decimal(5,2): por encima de 999,99 % PostgreSQL rechaza la OP con
            // un error del servidor. Un rendimiento así es un error de digitación, no una producción real.
            function (Validator $validator): void {
                $order = $this->route('production_order');
                $actualYield = $this->input('actual_yield_quantity');

                if (! $order instanceof ProductionOrder || $validator->errors()->has('actual_yield_quantity') || ! is_numeric($actualYield)) {
                    return;
                }

                $calculator = app(DecimalCalculator::class);
                $planned = (string) $order->quantity;

                if ($calculator->isPositive($planned)
                    && $calculator->cmp($calculator->mul($calculator->div($calculator->normalize($actualYield), $planned, 10), '100', 4), '999.99') > 0) {
                    $validator->errors()->add('actual_yield_quantity', __('El rendimiento real no puede superar 9,99 veces la cantidad proyectada de la orden. Revisa el valor.'));
                }
            },
            $this->somethingPackedCheck(),
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $calculator = app(DecimalCalculator::class);

                // Ya validados: algo se envasó o quedó como saldo (la regla anterior), y el rendimiento es obligatorio.
                $packaging = collect($this->input('packaging') ?? []);
                $remnantGallons = $this->input('remnant_quantity_gallons');
                $actualYield = $this->input('actual_yield_quantity');

                // Sin presentaciones (todo quedó como saldo) no hay nada que consultar.
                $plans = $packaging->isEmpty() ? collect() : ProductionOrderPackagingPlan::query()
                    ->whereIn('id', $packaging->pluck('id')->map(fn ($id) => (int) $id)->all())
                    ->with('productVariant:id,presentation_value')
                    ->get()
                    ->keyBy('id');

                $expectedYield = '0';

                foreach ($packaging as $packagingData) {
                    $plan = $plans->get((int) $packagingData['id']);
                    $actualUnits = (string) $packagingData['actual_units'];

                    if ($plan === null || ! $calculator->isPositive($actualUnits)) {
                        continue;
                    }

                    $presentationValue = (string) ($plan->productVariant?->presentation_value ?? '1');
                    $expectedYield = $calculator->add($expectedYield, $calculator->mul($actualUnits, $presentationValue, 4), 4);
                }

                if (app(ProductionOrderCompletionLines::class)->hasRemnant($remnantGallons)) {
                    $expectedYield = $calculator->add($expectedYield, (string) $remnantGallons, 4);
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

                // Firma el certificado quien puede completar órdenes (docs/MATRIZ_RBAC.md).
                if (! $user->can(Permission::ProductionOrdersComplete->value)) {
                    $validator->errors()->add('quality_responsible_user_id', 'El usuario seleccionado no tiene permiso para completar órdenes de producción.');
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
