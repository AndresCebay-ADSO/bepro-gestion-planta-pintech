<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Enums\AlertSeverity;
use App\Enums\AlertType;
use App\Models\Alert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Alert::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('status') || $this->input('status') === null || $this->input('status') === '') {
            $this->merge(['status' => 'active']);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['nullable', 'string', Rule::in(['active', 'resolved', 'all'])],
            'type' => ['nullable', 'string', Rule::enum(AlertType::class)],
            'severity' => ['nullable', 'string', Rule::enum(AlertSeverity::class)],
        ];
    }
}
