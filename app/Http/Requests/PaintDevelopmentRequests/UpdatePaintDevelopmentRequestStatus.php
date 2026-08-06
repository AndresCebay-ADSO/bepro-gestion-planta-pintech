<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

use App\Enums\PaintDevelopmentRequestStatus;
use App\Models\PaintDevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePaintDevelopmentRequestStatus extends FormRequest
{
    public function authorize(): bool
    {
        $request = $this->route('paintDevelopmentRequest');

        return $request instanceof PaintDevelopmentRequest
            && $this->user()?->can('updateStatus', $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(PaintDevelopmentRequestStatus::class)],
            'review_notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
