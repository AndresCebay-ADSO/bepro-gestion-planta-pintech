<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPaintDevelopmentRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', PaintDevelopmentRequest::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(PaintDevelopmentRequestStatus::class)],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
