<?php

declare(strict_types=1);

namespace App\Http\Requests\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Quotation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexQuotationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Quotation::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->user()?->hasRole('admin')) {
            $this->merge(['created_by' => null]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(QuotationStatus::class)],
            'created_by' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
