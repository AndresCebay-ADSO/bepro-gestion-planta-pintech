<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ProductionOrder;
use App\Models\ShrinkWrap;
use App\Models\ShrinkWrapTypeItem;
use App\Services\DecimalCalculator;
use App\Services\TimezoneService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro de termoencogido (3.8): una OP completada, un tipo activo y cuántas veces se aplicó.
 */
class StoreShrinkWrapRequest extends FormRequest
{
    /** Tope de las cantidades guardadas: columnas decimal(12,4). */
    private const MAX_QUANTITY = '99999999.9999';

    public function authorize(): bool
    {
        return $this->user()?->can('create', ShrinkWrap::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Solo completadas (antes no hay lotes que termoencoger, decisión del 2026-10-02) y dentro de la ventana de
            // ShrinkWrap::ORDER_WINDOW_MONTHS: la misma regla que el selector (ProductionOrder::scopeShrinkWrappable).
            'production_order_id' => [
                'bail',
                'required',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! ProductionOrder::query()->shrinkWrappable()->whereKey((int) $value)->exists()) {
                        $fail(__('La orden de producción debe estar completada y en los últimos :months meses.', [
                            'months' => ShrinkWrap::ORDER_WINDOW_MONTHS,
                        ]));
                    }
                },
            ],
            'shrink_wrap_type_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('shrink_wrap_types', 'id')->where('is_active', true),
            ],
            // Entero: cuántas veces se termoencogió. `integer` rechaza «1e3» y «1.5»; el tope de cada línea de la
            // receta lo revisa after(), porque depende de la cantidad por aplicación.
            'applications' => ['bail', 'required', 'integer', 'min:1', 'max:99999999'],
            // Fecha de planta: puede ser semanas después de completar la OP, pero no futura.
            'wrapped_at' => ['bail', 'required', 'date_format:Y-m-d', 'before_or_equal:'.$this->plantToday()],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->validateNotBeforeCompletion($validator),
            fn (Validator $validator) => $this->validateQuantitiesFit($validator),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shrink_wrap_type_id.exists' => __('El tipo de termoencogido no existe o está inactivo.'),
            'wrapped_at.before_or_equal' => __('La fecha no puede ser futura.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'production_order_id' => __('orden de producción'),
            'shrink_wrap_type_id' => __('tipo de termoencogido'),
            'applications' => __('aplicaciones'),
            'wrapped_at' => __('fecha'),
            'notes' => __('notas'),
        ];
    }

    /**
     * Antes de completar la OP no existían sus lotes (D3, decisión del 2026-10-05).
     */
    private function validateNotBeforeCompletion(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['production_order_id', 'wrapped_at'])) {
            return;
        }

        $completionDate = ProductionOrder::query()
            ->whereKey((int) $this->input('production_order_id'))
            ->value('completion_date');

        if ($completionDate !== null && (string) $this->input('wrapped_at') < substr((string) $completionDate, 0, 10)) {
            $validator->errors()->add('wrapped_at', __('La fecha no puede ser anterior a la fecha en que se completó la orden (:date).', [
                'date' => app(TimezoneService::class)->formatPlantDate(substr((string) $completionDate, 0, 10)),
            ]));
        }
    }

    /**
     * Sin tope de negocio (no se compara con las unidades producidas), pero sí técnico: lo que se descuenta de cada
     * materia prima debe caber en su columna. Si no, la base fallaría con un 500, como los que cerró #193.
     */
    private function validateQuantitiesFit(Validator $validator): void
    {
        if ($validator->errors()->hasAny(['shrink_wrap_type_id', 'applications'])) {
            return;
        }

        $calculator = app(DecimalCalculator::class);
        $applications = (string) (int) $this->input('applications');

        $items = ShrinkWrapTypeItem::query()
            ->where('shrink_wrap_type_id', (int) $this->input('shrink_wrap_type_id'))
            ->with('rawMaterial:id,code')
            ->get();

        foreach ($items as $item) {
            if ($calculator->cmp($calculator->mul((string) $item->quantity, $applications, 4), self::MAX_QUANTITY) > 0) {
                $validator->errors()->add('applications', __('Son demasiadas aplicaciones: el consumo de :code no cabe en el registro.', [
                    'code' => $item->rawMaterial->code,
                ]));

                return;
            }
        }
    }

    private function plantToday(): string
    {
        return app(TimezoneService::class)->nowInPlant()->toDateString();
    }
}
