<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\NumberType;
use App\Models\UnitOfMeasure;
use App\Services\DecimalCalculator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUnitOfMeasureRequest extends StoreUnitOfMeasureRequest
{
    public function authorize(): bool
    {
        $unit = $this->route('unit_of_measure');

        return $unit instanceof UnitOfMeasure
            && ($this->user()?->can('update', $unit) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $unit = $this->route('unit_of_measure');

        return [
            'code' => ['bail', 'required', 'string', 'max:20', Rule::unique('unit_of_measures', 'code')->ignore($unit?->id)],
            ...$this->sharedRules(),
            'confirm_factor_change' => ['sometimes', 'boolean'],
        ];
    }

    protected function defaultIsActive(): bool
    {
        $unit = $this->route('unit_of_measure');

        return $unit instanceof UnitOfMeasure ? $unit->is_active : true;
    }

    /**
     * Cambiar la equivalencia de una unidad en uso cambia lo que pedirán las OP que se creen después (las ya creadas
     * guardan sus cantidades) y el costo de los productos cuya fórmula de referencia la usa (se recalcula al guardar). La
     * pantalla lo avisa, pero la confirmación la exige el servidor.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            // Ni con confirmación: una unidad en uso que pasa de volumen a peso, o se queda sin equivalencia, deja sin
            // conversión las líneas de fórmula que ya la usan con otra unidad (no se podrían costear ni producir). Corregir
            // el valor sí se puede, y también darle su primera equivalencia (la «u» de envases): no rompe ninguna línea.
            // Otra base es otra unidad.
            function (Validator $validator): void {
                $unit = $this->route('unit_of_measure');

                if (! $unit instanceof UnitOfMeasure || $validator->errors()->isNotEmpty()) {
                    return;
                }

                $currentBasis = $this->basis($unit->to_kg_conversion, $unit->to_liter_conversion);

                if ($currentBasis !== null && $this->incomingBasis($unit) !== $currentBasis && $unit->affectsConversions()) {
                    $validator->errors()->add(
                        $unit->to_liter_conversion !== null ? 'to_liter_conversion' : 'to_kg_conversion',
                        __('Esta unidad está en uso: puedes corregir su equivalencia, pero no pasarla de volumen a peso (ni al revés) ni dejarla sin equivalencia. Para otra base, crea otra unidad.'),
                    );
                }
            },
            function (Validator $validator): void {
                $unit = $this->route('unit_of_measure');

                if (! $unit instanceof UnitOfMeasure || $validator->errors()->isNotEmpty() || $this->boolean('confirm_factor_change')) {
                    return;
                }

                if ($this->factorsChanged($unit) && $unit->affectsConversions()) {
                    $validator->errors()->add(
                        'confirm_factor_change',
                        __('Esta unidad está en uso: confirma el cambio de equivalencia. Las OP que se creen después y el costo de los productos que la usan se calcularán con el valor nuevo; las OP ya creadas no cambian.'),
                    );
                }
            },
        ];
    }

    /**
     * Por qué se convierte la unidad: a litros, a kilos o por ninguna (una unidad tiene a lo sumo una equivalencia).
     */
    private function basis(mixed $toKg, mixed $toLiter): ?string
    {
        return match (true) {
            $toLiter !== null && $toLiter !== '' => 'liter',
            $toKg !== null && $toKg !== '' => 'kg',
            default => null,
        };
    }

    /** La base con lo que trae la petición; una equivalencia que no viene no se actualiza. */
    private function incomingBasis(UnitOfMeasure $unit): ?string
    {
        return $this->basis(
            $this->has('to_kg_conversion') ? $this->input('to_kg_conversion') : $unit->to_kg_conversion,
            $this->has('to_liter_conversion') ? $this->input('to_liter_conversion') : $unit->to_liter_conversion,
        );
    }

    private function factorsChanged(UnitOfMeasure $unit): bool
    {
        $calculator = app(DecimalCalculator::class);

        foreach (['to_kg_conversion', 'to_liter_conversion'] as $factor) {
            // Un factor que no viene en la petición no se actualiza, así que no cuenta como cambio.
            if (! $this->has($factor)) {
                continue;
            }

            $current = $unit->{$factor};
            $incoming = $this->input($factor);

            if ($current === null || $incoming === null) {
                if ($current !== $incoming) {
                    return true;
                }

                continue;
            }

            if ($calculator->cmp((string) $current, (string) $incoming, NumberType::Factor->scale()) !== 0) {
                return true;
            }
        }

        return false;
    }
}
