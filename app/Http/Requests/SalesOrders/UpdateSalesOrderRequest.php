<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = $this->route('sales_order');

        return $this->user()?->can('update', $salesOrder) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = $this->route('sales_order');

        $validTransitions = array_map(
            fn (SalesOrderStatus $status) => $status->value,
            $salesOrder->status->nextTransitions()
        );

        return [
            'status' => [
                'sometimes',
                Rule::enum(SalesOrderStatus::class),
                function (string $attribute, mixed $value, \Closure $fail) use ($salesOrder, $validTransitions): void {
                    if ($value === $salesOrder->status->value) {
                        return;
                    }

                    if (! in_array($value, $validTransitions, true)) {
                        $fail('La transición de estado no es válida.');
                    }
                },
            ],
            'priority' => ['sometimes', Rule::enum(SalesOrderPriority::class)],
            'estimated_delivery_date' => ['sometimes', 'nullable', 'date'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shipping_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'client_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'client_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_nit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }
}
