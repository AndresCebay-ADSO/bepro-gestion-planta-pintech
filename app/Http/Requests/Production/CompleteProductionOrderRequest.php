<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\NumberType;
use App\Enums\Permission;
use App\Models\ProductionOrder;
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
            $this->operationalTimesRules(),
            [
                // Cada cantidad con el tipo de su columna (Cantidad 12,4 y Densidad 10,4): sin tope, 1e65 o 1e20 pasan
                // `numeric` y fallan en la calculadora o al guardar en PostgreSQL. El rendimiento real no se pide: lo
                // calcula el servidor (lo envasado más el sobrante).
                'quality_responsible_user_id' => ['bail', 'required', 'integer', 'exists:users,id'],
                'spillage_quantity' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
                'density_kg_per_gallon' => ['bail', 'required', ...NumberType::Density->rules(), 'min:0.0001'],
                'remnant_quantity_kg' => ['bail', 'nullable', ...NumberType::Quantity->rules(), 'min:0'],
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
            $this->remnantWeightCheck(),
            $this->somethingPackedCheck(),
            $this->operationalTimesCheck(),
            // El porcentaje de rendimiento se guarda en decimal(5,2): por encima de 999,99 % PostgreSQL rechaza la OP con
            // un error del servidor. Un rendimiento así es un error en las unidades envasadas, no una producción real.
            // Es la única comparación contra lo proyectado: salir con menos no es un error.
            function (Validator $validator): void {
                $order = $this->route('production_order');

                if (! $order instanceof ProductionOrder || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $calculator = app(DecimalCalculator::class);
                $lines = app(ProductionOrderCompletionLines::class);
                $packaging = $this->input('packaging');
                $realYield = $lines->realYield(
                    $order,
                    is_array($packaging) ? $packaging : [],
                    $lines->remnantGallons($this->input('remnant_quantity_kg'), $this->input('density_kg_per_gallon')),
                );
                $planned = (string) $order->quantity;

                if ($calculator->isPositive($planned)
                    && $calculator->cmp($calculator->mul($calculator->div($realYield, $planned, 10), '100', 4), '999.99') > 0) {
                    $validator->errors()->add('packaging', __('Lo envasado más el sobrante supera 9,99 veces la cantidad proyectada de la orden. Revisa las unidades envasadas.'));
                }
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
                'viscosity_ku' => 'viscosidad (KU)',
                'grinding_hg' => 'molienda (HG)',
                'quality_solids' => 'sólidos de calidad',
                'agitation_start_time' => 'hora de inicio de agitación',
                'agitation_end_time' => 'hora de fin de agitación',
                'packaging_start_time' => 'hora de inicio de envasado',
                'packaging_end_time' => 'hora de fin de envasado',
                'responsible_name' => 'responsable',
                'quality_responsible_user_id' => 'responsable de calidad',
                'spillage_quantity' => 'derrame (kg)',
                'density_kg_per_gallon' => 'peso por galón (kg/gal)',
                'remnant_quantity_kg' => 'sobrante pesado (kg)',
                'remnant_notes' => 'observaciones del saldo',
                'notes' => 'observaciones',
            ]
        );
    }
}
