<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Models\Warehouse;
use Illuminate\Foundation\Http\FormRequest;

class IndexWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Warehouse::class) ?? false;
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
