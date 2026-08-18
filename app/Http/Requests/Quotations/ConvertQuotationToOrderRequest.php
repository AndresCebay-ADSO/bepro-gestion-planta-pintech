<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Enums\SalesOrderPriority;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ConvertQuotationToOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Quotation $quotation */
        $quotation = $this->route('quotation');

        return $this->user()?->can('convertToOrder', $quotation) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'priority' => ['required', Rule::enum(SalesOrderPriority::class)],
            'required_date' => ['required', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
        ];
    }
}
