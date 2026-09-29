<?php

declare(strict_types=1);

namespace App\Http\Requests\Catalogs;

use App\Enums\RawMaterialType;
use App\Models\RawMaterialCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRawMaterialCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', RawMaterialCategory::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,all'],
            'type' => ['nullable', Rule::enum(RawMaterialType::class)],
        ];
    }
}
