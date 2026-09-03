<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\FinishedInventory;
use Illuminate\Foundation\Http\FormRequest;

class IndexFinishedInventoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', FinishedInventory::class) ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
        ];
    }
}
