<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

use App\Models\PaintDevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;

class StorePaintDevelopmentRequest extends FormRequest
{
    use PaintDevelopmentRequestRules;

    public function authorize(): bool
    {
        return $this->user()?->can('create', PaintDevelopmentRequest::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
