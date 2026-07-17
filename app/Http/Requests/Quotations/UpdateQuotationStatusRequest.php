<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Enums\QuotationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuotationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $quotation = $this->route('quotation');

        return $quotation !== null
            && $this->user()?->can('updateStatus', $quotation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(QuotationStatus::class)],
        ];
    }
}
