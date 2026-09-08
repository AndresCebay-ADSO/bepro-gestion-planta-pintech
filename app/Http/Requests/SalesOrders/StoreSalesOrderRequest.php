<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Models\ProductVariant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'comercial']) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'priority' => ['required', Rule::enum(SalesOrderPriority::class)],
            'required_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
            'client_business_name' => ['nullable', 'string', 'max:255'],
            'client_nit' => ['nullable', 'string', 'max:20'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.product_variant_id' => [
                'nullable',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;
                    }

                    $index = explode('.', $attribute)[1] ?? null;

                    if ($index === null) {
                        $fail('Error interno al validar la variante.');

                        return;
                    }

                    $productId = $this->input("items.{$index}.product_id");

                    $exists = ProductVariant::where('id', $value)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->where('product_id', $productId)
                        ->exists();

                    if (! $exists) {
                        $fail('La presentación no pertenece al producto seleccionado.');
                    }
                },
            ],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Debes agregar al menos un producto a la orden de venta.',
            'items.min' => 'Debes agregar al menos un producto a la orden de venta.',
            'items.*.product_id.required' => 'Debes seleccionar un producto.',
            'items.*.quantity.required' => 'La cantidad es obligatoria.',
            'items.*.quantity.min' => 'La cantidad debe ser mayor a cero.',
            'required_date.after_or_equal' => 'La fecha requerida debe ser igual o posterior a la fecha actual.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'priority' => 'prioridad',
            'required_date' => 'fecha requerida',
            'notes' => 'observaciones',
            'shipping_address' => 'dirección de entrega',
            'client_business_name' => 'razón social',
            'client_nit' => 'NIT o identificación',
            'client_contact_name' => 'contacto del cliente',
            'client_phone' => 'teléfono del cliente',
            'items' => 'productos',
            'items.*.product_id' => 'producto',
            'items.*.product_variant_id' => 'presentación',
            'items.*.quantity' => 'cantidad',
        ];
    }
}
