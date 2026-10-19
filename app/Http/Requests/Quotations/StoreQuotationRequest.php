<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Enums\NumberType;
use App\Enums\PaymentMethod;
use App\Enums\QuotationItemType;
use App\Enums\QuotationValidity;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Rules\PlantDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
    /** Productos por cotización: el PDF lleva unos 8 por hoja y el promedio es de 4 a 6. */
    public const MAX_ITEMS = 50;

    /** @var array{client_id: int|null, product_ids: array<int, int>, variant_ids: array<int, int>}|null */
    private ?array $kept = null;

    /** @var Collection<int, ProductVariant>|null */
    private ?Collection $variantsToValidate = null;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Quotation::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }

    /**
     * @return array<string, mixed>
     */
    protected function sharedRules(): array
    {
        $kept = $this->kept();

        return [
            'client_id' => [
                'required',
                Rule::exists('clients', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->when($kept['client_id'] !== null, fn ($q) => $q->orWhere('id', $kept['client_id']))),
            ],
            'client_business_name' => ['nullable', 'string', 'max:255'],
            'client_nit' => ['nullable', 'string', 'max:30'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'technology' => ['nullable', 'string', 'max:100'],
            'line' => ['nullable', 'string', 'max:100'],
            'thickness_mils' => ['nullable', 'string', 'max:50'],
            'application_method' => ['nullable', 'string', 'max:100'],
            // Sin fechas futuras; si llega vacía, QuotationService pone hoy en planta (D-COT2).
            'quotation_date' => ['bail', 'nullable', PlantDate::make()->notFuture()->allowing($this->savedQuotationDate())],
            'validity_days' => ['nullable', 'integer', Rule::enum(QuotationValidity::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'delivery_time' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            ...$this->pricingRules(),
        ];
    }

    /**
     * Lo que decide el precio: el IVA y las líneas. Lo comparten guardar y la vista previa del formulario.
     *
     * @return array<string, mixed>
     */
    protected function pricingRules(): array
    {
        $kept = $this->kept();
        $items = $this->input('items');
        $document = [
            'iva_percentage' => ['required', ...NumberType::Percentage->rules('100'), 'min:0'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
        ];

        // Con más filas del tope solo se informa el tope: validar fila por fila costaría una consulta por fila.
        if (is_array($items) && count($items) > self::MAX_ITEMS) {
            return $document;
        }

        return [
            ...$document,
            'items.*.product_id' => [
                'required',
                Rule::exists('products', 'id')->where(fn ($query) => $query
                    ->where('is_active', true)
                    ->orWhereIn('id', $kept['product_ids'])),
            ],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail) use ($kept): void {
                    $index = explode('.', $attribute)[1] ?? null;

                    if ($index === null) {
                        $fail('Error interno al validar la variante.');

                        return;
                    }

                    $variant = is_numeric($value) ? $this->variantsToValidate()->get((int) $value) : null;

                    $exists = $variant !== null
                        && (int) $variant->product_id === (int) $this->input("items.{$index}.product_id")
                        && ((bool) $variant->is_active || in_array((int) $variant->id, $kept['variant_ids'], true));

                    if (! $exists) {
                        $fail('La presentación no pertenece al producto seleccionado.');
                    }
                },
            ],
            'items.*.type' => ['nullable', Rule::enum(QuotationItemType::class)],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', ...NumberType::Units->rules(), 'min:1'],
            'items.*.price_adjustment_pct' => ['nullable', ...NumberType::Percentage->rules('99.99'), 'min:-100'],
            // En pesos enteros, como los ve el cliente (P4b-3), con el tope del tipo Dinero.
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:'.NumberType::Money->max()],
        ];
    }

    /**
     * Las presentaciones de todas las líneas, en una sola consulta: la vista previa valida con cada pausa al escribir.
     *
     * @return Collection<int, ProductVariant>
     */
    private function variantsToValidate(): Collection
    {
        if ($this->variantsToValidate === null) {
            $ids = collect((array) $this->input('items', []))
                ->map(fn (mixed $item): mixed => is_array($item) ? ($item['product_variant_id'] ?? null) : null)
                ->filter(fn (mixed $id): bool => filter_var($id, FILTER_VALIDATE_INT) !== false)
                ->map(fn (mixed $id): int => (int) $id)
                ->unique()
                ->values();

            $this->variantsToValidate = ProductVariant::query()
                ->whereIn('id', $ids)
                ->get(['id', 'product_id', 'is_active'])
                ->keyBy('id');
        }

        return $this->variantsToValidate;
    }

    /**
     * Las presentaciones que la cotización ya tenía, para calcular sus precios sin volver a consultarlas.
     *
     * @return array<int, int>
     */
    public function keptVariantIds(): array
    {
        return $this->kept()['variant_ids'];
    }

    /**
     * @return array{client_id: int|null, product_ids: array<int, int>, variant_ids: array<int, int>}
     */
    private function kept(): array
    {
        return $this->kept ??= $this->keptRecords();
    }

    /**
     * Cliente, productos y presentaciones que el documento ya tiene: se aceptan aunque estén inactivos, para que un
     * documento abierto siga su curso (docs/POLITICA_ELIMINACION.md §3.1). Al crear no hay ninguno.
     *
     * @return array{client_id: int|null, product_ids: array<int, int>, variant_ids: array<int, int>}
     */
    protected function keptRecords(): array
    {
        return ['client_id' => null, 'product_ids' => [], 'variant_ids' => []];
    }

    /**
     * La fecha que la cotización ya tiene: se acepta aunque sea futura, para que un borrador de antes de la regla se
     * pueda volver a guardar sin cambiarla. Al crear no hay ninguna.
     */
    protected function savedQuotationDate(): ?string
    {
        return null;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debes agregar al menos un producto a la cotización.',
            'items.min' => 'Debes agregar al menos un producto a la cotización.',
            'items.max' => 'Una cotización lleva como mucho '.self::MAX_ITEMS.' productos.',
            'items.*.product_id.required' => 'Debes seleccionar un producto.',
            'items.*.product_variant_id.required' => 'Debes seleccionar la presentación del producto.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'client_business_name' => 'razón social',
            'client_nit' => 'NIT o identificación',
            'client_contact_name' => 'contacto del cliente',
            'client_phone' => 'teléfono del cliente',
            'technology' => 'tecnología',
            'line' => 'línea',
            'thickness_mils' => 'espesor',
            'application_method' => 'método de aplicación',
            'quotation_date' => 'fecha de cotización',
            'validity_days' => 'días de validez',
            'payment_method' => 'forma de pago',
            'delivery_time' => 'tiempo de entrega',
            'area' => 'área',
            'notes' => 'observaciones',
            'iva_percentage' => 'porcentaje de IVA',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.product_variant_id' => 'presentación',
            'items.*.type' => 'tipo de ítem',
            'items.*.description' => 'descripción',
            'items.*.color' => 'color',
            'items.*.quantity' => 'cantidad',
            'items.*.price_adjustment_pct' => 'porcentaje de ajuste de precio',
            'items.*.unit_price' => 'precio unitario',
        ];
    }
}
