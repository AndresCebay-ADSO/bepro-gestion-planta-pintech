<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\RemnantStatus;
use App\Models\ProductionRemnant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexRemnantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductionRemnant::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(RemnantStatus::class)],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
        ];
    }
}
