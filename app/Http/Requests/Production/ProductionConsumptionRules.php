<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\NumberType;
use App\Enums\QualityMeasurement;
use App\Enums\RawMaterialType;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\RawMaterial;
use App\Rules\RawMaterialOfType;
use App\Services\DecimalCalculator;
use App\Services\Production\ProductionOrderCompletionLines;
use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ProductionConsumptionRules
{
    protected function resolveProductionOrderId(): ?int
    {
        $order = $this->route('production_order');

        if ($order instanceof ProductionOrder) {
            return $order->id;
        }

        if (is_numeric($order)) {
            return (int) $order;
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function consumptionRules(?int $orderId = null): array
    {
        $orderId ??= $this->resolveProductionOrderId();
        $scopedOrderId = $orderId ?? 0;

        return [
            'ingredients' => ['required', 'array'],
            'ingredients.*.id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('production_order_details', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            // El navegador convierte la unidad con floats (B51), pero redondea a 4 decimales antes de enviar
            // (production-ingredient-quantity.ts): `decimal` no rechaza nada legítimo y sí `1.123456` y `1e3` (OPL-08).
            'ingredients.*.actual_quantity' => ['bail', 'required', ...NumberType::Quantity->rules(), 'min:0'],
            'packaging' => ['array'],
            'packaging.*.id' => [
                'bail',
                'required',
                'integer',
                'distinct',
                Rule::exists('production_order_packaging_plan', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            // Unidades enteras (D-OP1): se cuentan envases de la presentación; el ¼ ya está en su tamaño.
            'packaging.*.actual_units' => ['bail', 'required', ...NumberType::Units->rules(), 'min:0'],
            // Empaque (3.7). Vacío = tantos como unidades envasadas. Sin tope de negocio: la merma (un envase dañado al
            // llenar, una etiqueta mal pegada) es costo del lote (decisión del 2026-09-30).
            'packaging.*.new_containers_used' => ['bail', 'nullable', ...NumberType::Units->rules(), 'min:0', $this->requiresPackedUnits()],
            'packaging.*.labels_used' => ['bail', 'nullable', ...NumberType::Units->rules(), 'min:0', $this->requiresPackedUnits()],
            'packaging.*.label_raw_material_id' => [
                'nullable',
                'integer',
                Rule::exists('raw_materials', 'id'),
                new RawMaterialOfType(RawMaterialType::Label),
                $this->activeOrCurrentPlanLabel($scopedOrderId),
            ],
        ];
    }

    /**
     * Los ajustes de línea y los saldos consumidos que mostraba la pantalla al confirmar. No se guardan: se comparan con
     * los de la OP bloqueada, porque quien completa firma el costo que vio (P6-2, ProductionOrderCompletionLines).
     *
     * @return array<string, mixed>
     */
    protected function seenLinesRules(): array
    {
        $scopedOrderId = $this->resolveProductionOrderId() ?? 0;

        return [
            'line_adjustment_ids' => ['present', 'array'],
            'line_adjustment_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('production_order_line_adjustments', 'id')->where('production_order_id', $scopedOrderId),
            ],
            'remnant_consumption_ids' => ['present', 'array'],
            'remnant_consumption_ids.*' => [
                'bail',
                'integer',
                'distinct',
                Rule::exists('remnant_consumptions', 'id')->where('target_order_id', $scopedOrderId),
            ],
        ];
    }

    /**
     * D-OPL1: no se entrega una OP sin envasar nada ni dejar saldo. Va antes de la regla de rendimiento, que con todo en
     * 0 daría un «esperado: 0» que no explica el problema. Corre solo con los datos ya válidos.
     *
     * @return callable(Validator): void
     */
    protected function somethingPackedCheck(): callable
    {
        return function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $packaging = $this->input('packaging');

            if (! app(ProductionOrderCompletionLines::class)->packedSomething(is_array($packaging) ? $packaging : [], $this->input('remnant_quantity_gallons'))) {
                $validator->errors()->add('packaging', __(ProductionOrderCompletionLines::NOTHING_PACKED_MESSAGE));
            }
        };
    }

    /**
     * Mediciones de calidad y responsable, iguales al enviar a revisión y al completar. La calidad usa el mismo tipo y
     * tope que el rango del producto (QualityMeasurement); el responsable cabe en su columna de 150 (OPL-04).
     *
     * @return array<string, list<string>>
     */
    protected function qualityAndResponsibleRules(): array
    {
        return [
            'viscosity_ku' => ['bail', 'nullable', ...QualityMeasurement::Viscosity->rules()],
            'grinding_hg' => ['bail', 'nullable', ...QualityMeasurement::Fineness->rules()],
            'quality_solids' => ['bail', 'nullable', ...QualityMeasurement::Solids->rules()],
            'responsible_name' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * Sin unidades envasadas la OP no crea lote, y no hay a qué cargarle envases ni etiquetas: quedarían anotados sin
     * descontarse. Un envase dañado suelto que controla inventario va como salida manual con una nota, como cualquier
     * merma; una etiqueta sin control de inventario no tiene saldo que ajustar (no admite movimientos manuales).
     */
    private function requiresPackedUnits(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $index = explode('.', $attribute)[1] ?? null;
            $actualUnits = $this->input("packaging.{$index}.actual_units");

            // Las unidades se validan en su propio campo, y `bail` solo corta las reglas de ese campo: esta regla puede
            // leerlas aunque ya tengan su error. Por eso solo compara números escritos sin notación científica (un float
            // como 1e65 se escribe «1.0E+65»), que nunca hacen fallar a la calculadora. No repite los límites de la
            // columna: de eso se ocupan `decimal` y `max`.
            $plainDecimal = function (mixed $number): bool {
                $text = is_string($number) ? trim($number) : (is_int($number) || is_float($number) ? (string) $number : null);

                return $text !== null && is_numeric($text) && stripos($text, 'e') === false;
            };

            if (! $plainDecimal($value) || ! $plainDecimal($actualUnits)) {
                return;
            }

            $calculator = app(DecimalCalculator::class);

            if ($calculator->isPositive($calculator->normalize($value))
                && ! $calculator->isPositive($calculator->normalize($actualUnits))) {
                $fail(__('Sin unidades envasadas no hay lote al que cargarlos: deja el campo vacío. Un envase dañado que controla inventario se registra como salida manual con una nota.'));
            }
        };
    }

    /**
     * La etiqueta de un plan debe estar activa, salvo que sea la que el plan ya tiene: una etiqueta desactivada después de
     * agregar el plan no debe impedir guardar ni completar la OP.
     */
    private function activeOrCurrentPlanLabel(int $orderId): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($orderId): void {
            $index = explode('.', $attribute)[1] ?? null;
            $planId = (int) $this->input("packaging.{$index}.id");

            $current = ProductionOrderPackagingPlan::query()
                ->where('production_order_id', $orderId)
                ->whereKey($planId)
                ->value('label_raw_material_id');

            if ((int) $current === (int) $value) {
                return;
            }

            if (! RawMaterial::query()->whereKey((int) $value)->where('is_active', true)->exists()) {
                $fail(__('La etiqueta elegida está inactiva.'));
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ingredients.required' => 'Debes registrar el consumo de ingredientes de la orden.',
            'ingredients.*.id.distinct' => 'No puedes repetir el mismo ingrediente en la lista.',
            'packaging.*.id.distinct' => 'No puedes repetir la misma presentación de empaque en la lista.',
            // Una línea que ya no está en la orden: otro usuario la quitó después de que se abrió la pantalla (C4).
            'packaging.*.id.exists' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE),
            'line_adjustment_ids.*.exists' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE),
            'remnant_consumption_ids.*.exists' => __(ProductionOrderCompletionLines::ORDER_CHANGED_MESSAGE),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function consumptionAttributes(): array
    {
        return [
            'ingredients' => 'ingredientes consumidos',
            'ingredients.*.id' => 'ingrediente',
            'ingredients.*.actual_quantity' => 'cantidad real utilizada',
            'packaging' => 'empaques utilizados',
            'packaging.*.id' => 'plan de empaque',
            'packaging.*.actual_units' => 'unidades reales envasadas',
            'packaging.*.new_containers_used' => 'envases nuevos usados',
            'packaging.*.labels_used' => 'etiquetas usadas',
            'packaging.*.label_raw_material_id' => 'etiqueta',
            'line_adjustment_ids' => 'ajustes de línea',
            'line_adjustment_ids.*' => 'ajuste de línea',
            'remnant_consumption_ids' => 'saldos consumidos',
            'remnant_consumption_ids.*' => 'saldo consumido',
        ];
    }
}
