<?php

declare(strict_types=1);

namespace App\Http\Requests\SalesOrders;

use App\Enums\SalesOrderPriority;
use App\Models\SalesOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSalesOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var SalesOrder $salesOrder */
        $salesOrder = $this->route('sales_order');

        return $this->user()?->can('edit', $salesOrder) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
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
