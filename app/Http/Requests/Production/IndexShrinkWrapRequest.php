<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Models\ShrinkWrap;
use Illuminate\Foundation\Http\FormRequest;

class IndexShrinkWrapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ShrinkWrap::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'shrink_wrap_type_id' => ['nullable', 'integer', 'exists:shrink_wrap_types,id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
