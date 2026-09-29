<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Models\ProductCategory;
use Illuminate\Foundation\Http\FormRequest;

class IndexProductCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,all'],
        ];
    }
}
