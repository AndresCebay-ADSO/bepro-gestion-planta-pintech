<?php

declare(strict_types=1);

namespace App\Http\Requests\Formulas;

use App\Models\Formula;
use Illuminate\Foundation\Http\FormRequest;

class IndexFormulaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Formula::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:active,inactive,all'],
        ];
    }
}
