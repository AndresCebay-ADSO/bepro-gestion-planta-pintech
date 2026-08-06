<?php

declare(strict_types=1);

namespace App\Http\Requests\PaintDevelopmentRequests;

use App\Models\PaintDevelopmentRequest;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePaintDevelopmentRequest extends FormRequest
{
    use PaintDevelopmentRequestRules;

    public function authorize(): bool
    {
        $request = $this->route('paintDevelopmentRequest');

        return $request instanceof PaintDevelopmentRequest
            && $this->user()?->can('update', $request);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules();
    }
}
