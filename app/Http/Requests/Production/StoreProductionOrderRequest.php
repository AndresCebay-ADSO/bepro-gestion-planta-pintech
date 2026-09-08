<?php

declare(strict_types=1);

namespace App\Http\Requests\Production;

use App\Enums\WarehouseType;
use App\Models\ProductionOrder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductionOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ProductionOrder::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'formula_id' => [
                'required',
                'integer',
                Rule::exists('formulas', 'id')
                    ->where('product_id', $this->input('product_id'))
                    ->where('is_active', true)
                    ->whereNull('deleted_at'),
            ],
            'warehouse_id' => [
                'required',
                'integer',
                Rule::exists('warehouses', 'id')
                    ->where('type', WarehouseType::Factory->value)
                    ->where('is_active', true),
            ],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'planned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'packaging' => ['nullable', 'array'],
            'packaging.*.product_variant_id' => [
                'required_with:packaging',
                'integer',
                'distinct',
                Rule::exists('product_variants', 'id')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')
                    ->when($this->input('product_id') !== null, fn ($query) => $query->where('product_id', $this->input('product_id'))),
            ],
            'packaging.*.planned_units' => ['required_with:packaging', 'numeric', 'min:0.01'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'formula_id.exists' => 'La fórmula seleccionada no es válida, no pertenece al producto o no se encuentra disponible.',
            'warehouse_id.exists' => 'El almacén seleccionado debe ser de tipo fábrica y estar activo.',
            'packaging.*.product_variant_id.distinct' => 'No puedes repetir la misma presentación de empaque en la planificación.',
            'packaging.*.product_variant_id.exists' => 'La presentación seleccionada no es válida, no pertenece al producto o no se encuentra disponible.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'product_id' => 'producto',
            'formula_id' => 'fórmula',
            'warehouse_id' => 'almacén de producción',
            'quantity' => 'cantidad a producir',
            'planned_date' => 'fecha planificada',
            'notes' => 'observaciones',
            'packaging' => 'empaques planificados',
            'packaging.*.product_variant_id' => 'presentación de empaque',
            'packaging.*.planned_units' => 'unidades planificadas',
        ];
    }
}
