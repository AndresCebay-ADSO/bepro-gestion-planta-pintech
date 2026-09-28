<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

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
     * guardan sus cantidades). La pantalla lo avisa, pero la confirmación la exige el servidor.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $unit = $this->route('unit_of_measure');

                if (! $unit instanceof UnitOfMeasure || $validator->errors()->isNotEmpty() || $this->boolean('confirm_factor_change')) {
                    return;
                }

                if ($this->factorsChanged($unit) && $unit->affectsConversions()) {
                    $validator->errors()->add(
                        'confirm_factor_change',
                        __('Esta unidad está en uso: confirma el cambio de equivalencia. Las OP que se creen después calcularán con el valor nuevo; las ya creadas no cambian.'),
                    );
                }
            },
        ];
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

            if ($calculator->cmp((string) $current, (string) $incoming) !== 0) {
                return true;
            }
        }

        return false;
    }
}
