<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderStatus;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $salesOrder = $this->route('sales_order');

        return $salesOrder instanceof SalesOrder
            && ($this->user()?->can('updateStatus', $salesOrder) ?? false);
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
                'required',
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
        ];
    }
}
