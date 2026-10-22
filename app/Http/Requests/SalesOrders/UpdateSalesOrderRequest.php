<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Http\Requests\Concerns\ClientContactRules;
use App\Models\Client;
use App\Models\SalesOrder;
use App\Rules\PlantDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    use ClientContactRules;

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
        return [
            'priority' => ['sometimes', Rule::enum(SalesOrderPriority::class)],
            'estimated_delivery_date' => ['sometimes', 'bail', 'nullable', $this->estimatedDeliveryDateRule()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shipping_address' => ['sometimes', 'nullable', 'string', 'max:'.Client::SHIPPING_ADDRESS_MAX_LENGTH],
            'client_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_phone' => ['sometimes', ...$this->phoneRules()],
            'client_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_nit' => ['sometimes', ...$this->nitRules()],
        ];
    }

    /**
     * No antes del día en que se creó el pedido (D-PV3). La fecha que el pedido ya tiene se acepta igual: el formulario
     * la reenvía aunque solo cambien las notas, y una de antes de la regla no debe dejar el pedido sin poder guardarse.
     */
    private function estimatedDeliveryDateRule(): PlantDate
    {
        $salesOrder = $this->route('sales_order');

        if (! $salesOrder instanceof SalesOrder) {
            return PlantDate::make();
        }

        $rule = PlantDate::make()->allowing($salesOrder->estimated_delivery_date?->toDateString());
        $createdOn = $salesOrder->createdOnPlantDay();

        return $createdOn === null ? $rule : $rule->notBefore($createdOn, __('la fecha del pedido'));
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'status' => 'estado',
            'priority' => 'prioridad',
            'estimated_delivery_date' => 'fecha estimada de entrega',
            'notes' => 'observaciones',
            'shipping_address' => 'dirección de entrega',
            'client_contact_name' => 'contacto del cliente',
            'client_phone' => 'teléfono del cliente',
            'client_business_name' => 'razón social',
            'client_nit' => 'NIT o identificación',
        ];
    }
}
