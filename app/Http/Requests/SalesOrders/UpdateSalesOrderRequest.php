<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Models\SalesOrder;
use App\Rules\PlantDate;
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
        return [
            'priority' => ['sometimes', Rule::enum(SalesOrderPriority::class)],
            'estimated_delivery_date' => ['sometimes', 'bail', 'nullable', $this->estimatedDeliveryDateRule()],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'shipping_address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'client_contact_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_phone' => ['sometimes', 'nullable', 'string', 'max:20'],
            'client_business_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'client_nit' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * No antes del día en que se creó el pedido (D-PV3).
     */
    private function estimatedDeliveryDateRule(): PlantDate
    {
        $rule = PlantDate::make();
        $salesOrder = $this->route('sales_order');
        $createdOn = $salesOrder instanceof SalesOrder ? $salesOrder->createdOnPlantDay() : null;

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
