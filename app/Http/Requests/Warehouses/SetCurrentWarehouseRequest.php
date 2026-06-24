<?php

declare(strict_types=1);

namespace App\Http\Requests\Warehouses;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetCurrentWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')],
        ];
    }
}
