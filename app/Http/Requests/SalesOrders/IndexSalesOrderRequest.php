<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', SalesOrder::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::enum(SalesOrderStatus::class)],
            'priority' => ['nullable', 'string', Rule::enum(SalesOrderPriority::class)],
            'date_from' => ['nullable', 'date', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
