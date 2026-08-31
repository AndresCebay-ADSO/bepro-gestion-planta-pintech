<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\ProductionOrderStatus;
use App\Models\ProductionOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexProductionOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', ProductionOrder::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::enum(ProductionOrderStatus::class)],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
            'completed_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'completed_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:completed_from'],
        ];
    }
}
