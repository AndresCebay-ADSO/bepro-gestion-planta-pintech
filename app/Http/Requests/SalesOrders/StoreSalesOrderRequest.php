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
}
