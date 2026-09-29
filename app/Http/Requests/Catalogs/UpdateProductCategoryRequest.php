<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\ProductCategory;
use Illuminate\Validation\Rule;

class UpdateProductCategoryRequest extends StoreProductCategoryRequest
{
    public function authorize(): bool
    {
        $category = $this->route('product_category');

        return $category instanceof ProductCategory
            && ($this->user()?->can('update', $category) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('product_category');

        return [
            'name' => ['bail', 'required', 'string', 'max:100', Rule::unique('product_categories', 'name')->ignore($category?->id)],
            ...$this->sharedRules(),
        ];
    }

    protected function defaultIsActive(): bool
    {
        $category = $this->route('product_category');

        return $category instanceof ProductCategory ? $category->is_active : true;
    }
}
