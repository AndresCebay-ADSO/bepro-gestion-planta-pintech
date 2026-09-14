<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CostsUpdate->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sales_margin' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'sales_price' => ['nullable', 'numeric', 'gt:0'],
        ];
    }
}
