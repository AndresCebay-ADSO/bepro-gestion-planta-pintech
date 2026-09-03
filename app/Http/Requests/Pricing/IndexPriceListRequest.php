<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use Illuminate\Foundation\Http\FormRequest;

class IndexPriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasAnyRole(['admin', 'comercial']) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
