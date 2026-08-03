<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return $this->user()?->can('update', $product) ?? false;
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
