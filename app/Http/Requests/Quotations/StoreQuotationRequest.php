<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Enums\PaymentMethod;
use App\Enums\QuotationItemType;
use App\Enums\QuotationValidity;
use App\Models\ProductVariant;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreQuotationRequest extends FormRequest
{
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
        return [
            'client_id' => ['required', Rule::exists('clients', 'id')->whereNull('deleted_at')],
            'client_business_name' => ['nullable', 'string', 'max:255'],
            'client_nit' => ['nullable', 'string', 'max:30'],
            'client_contact_name' => ['nullable', 'string', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:30'],
            'technology' => ['nullable', 'string', 'max:100'],
            'line' => ['nullable', 'string', 'max:100'],
            'thickness_mils' => ['nullable', 'string', 'max:50'],
            'application_method' => ['nullable', 'string', 'max:100'],
            'quotation_date' => ['nullable', 'date'],
            'validity_days' => ['nullable', 'integer', Rule::enum(QuotationValidity::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'delivery_time' => ['nullable', 'string', 'max:100'],
            'area' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'iva_percentage' => ['required', 'numeric', 'min:0', 'max:100'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', Rule::exists('products', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'items.*.product_variant_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $index = explode('.', $attribute)[1] ?? null;

                    if ($index === null) {
                        $fail('Error interno al validar la variante.');

                        return;
                    }

                    $productId = $this->input("items.{$index}.product_id");

                    $exists = ProductVariant::query()
                        ->where('id', $value)
                        ->where('product_id', $productId)
                        ->where('is_active', true)
                        ->whereNull('deleted_at')
                        ->exists();

                    if (! $exists) {
                        $fail('La presentación no pertenece al producto seleccionado.');
                    }
                },
            ],
            'items.*.type' => ['nullable', Rule::enum(QuotationItemType::class)],
            'items.*.description' => ['nullable', 'string', 'max:255'],
            'items.*.color' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.0001'],
            'items.*.price_adjustment_pct' => ['nullable', 'numeric', 'min:-100', 'max:500'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
