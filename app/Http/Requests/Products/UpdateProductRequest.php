<?php

declare(strict_types=1);

namespace App\Http\Requests\Products;

use Illuminate\Validation\Rule;

class UpdateProductRequest extends StoreProductRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $product = $this->route('product');
        $productId = is_object($product) ? $product->id : $product;

        return array_merge(
            [
                'code' => [
                    'bail',
                    'nullable',
                    'string',
                    'max:50',
                    Rule::unique('products', 'code')->ignore($productId),
                ],
            ],
            $this->baseRules()
        );
    }
}
