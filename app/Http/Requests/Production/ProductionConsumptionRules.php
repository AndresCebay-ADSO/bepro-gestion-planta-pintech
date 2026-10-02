<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\RawMaterialType;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderPackagingPlan;
use App\Models\RawMaterial;
use App\Rules\RawMaterialOfType;
use App\Services\DecimalCalculator;
use Closure;
use Illuminate\Validation\Rule;

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
                'required',
                'distinct',
                Rule::exists('production_order_details', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            // Sin `decimal`: el navegador la multiplica por el factor de conversión y puede mandar ruido de floats. El tope
            // es el de la columna decimal(12,4): frena valores como «1e20», que pasan `numeric` y `min:0`.
            'ingredients.*.actual_quantity' => ['bail', 'required', 'numeric', 'min:0', 'max:99999999.9999'],
            'packaging' => ['array'],
            'packaging.*.id' => [
                'required',
                'distinct',
                Rule::exists('production_order_packaging_plan', 'id')
                    ->where('production_order_id', $scopedOrderId),
            ],
            // Formato decimal: `numeric` deja pasar «1e1», que no es una cantidad que alguien escriba a propósito.
            'packaging.*.actual_units' => ['bail', 'required', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999'],
            // Empaque (3.7). Vacío = tantos como unidades envasadas. Sin tope: la merma (un envase dañado al llenar, una
            // etiqueta mal pegada) es costo del lote (decisión del 2026-09-30).
            'packaging.*.new_containers_used' => ['bail', 'nullable', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999', $this->requiresPackedUnits()],
            'packaging.*.labels_used' => ['bail', 'nullable', 'numeric', 'min:0', 'decimal:0,4', 'max:99999999.9999', $this->requiresPackedUnits()],
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
        ];
    }
}
