<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Models\FinishedInventoryMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexFinishedInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinishedInventoryMovement::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'type' => ['nullable', 'string', Rule::enum(InventoryMovementType::class)],
            'reason' => ['nullable', 'string', Rule::enum(FinishedInventoryMovementReason::class)],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
