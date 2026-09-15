<?php

declare(strict_types=1);

namespace App\Http\Requests\Pricing;

use App\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;

class IndexCostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(Permission::CostsView->value) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
        ];
    }
}
