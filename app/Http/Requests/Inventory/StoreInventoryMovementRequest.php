<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\NumberType;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Rules\PlantDate;
use App\Services\InventoryService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInventoryMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', InventoryMovement::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'raw_material_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('raw_materials', 'id')->where('is_active', true),
                // Sin control de inventario (agua, etiquetas) no hay saldo: un lote suyo nunca se descontaría.
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (RawMaterial::query()->whereKey((int) $value)->where('tracks_inventory', false)->exists()) {
                        $fail(__(InventoryService::UNTRACKED_MATERIAL_MESSAGE));
                    }
                },
            ],
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'batch_id' => ['nullable', 'integer', Rule::exists('inventory_batches', 'id')],
            'production_order_id' => ['prohibited'],
            'type' => ['bail', 'required', Rule::in(['entry', 'exit'])],
            'quantity' => ['bail', 'required', ...NumberType::Quantity->rules(), 'gt:0'],
            'cost_price' => [
                'bail',
                Rule::requiredIf(fn () => $this->input('type') === 'entry'),
                'nullable',
                // Con 2 decimales (P5-4) y mayor que 0: un lote a precio 0 abarata las OP que lo consumen (D3).
                ...NumberType::Money->rules(),
                'gt:0',
            ],
            // Día de planta, sin futuras (D1). En una entrada, tampoco más de un mes atrás (D1b): también es el
            // `entry_date` del lote nuevo, que ordena el FIFO.
            'movement_date' => ['bail', 'required', $this->movementDateRule()],
            'lot_number' => ['nullable', 'string', 'max:50'],
            'supplier' => ['nullable', 'string', 'max:150'],
            'expiry_date' => ['bail', 'nullable', PlantDate::make(), 'after_or_equal:movement_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'created_by' => ['prohibited'],
        ];
    }

    private function movementDateRule(): PlantDate
    {
        $rule = PlantDate::make()->notFuture();

        return $this->input('type') === InventoryMovementType::Entry->value
            ? $rule->notBefore(InventoryMovement::oldestEntryDate(), __('la fecha de hace un mes'))
            : $rule;
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $batchId = $this->input('batch_id');
                if ($batchId === null || $batchId === '') {
                    if ($this->input('type') === 'entry') {
                        $lotNumber = $this->input('lot_number');

                        if ($lotNumber === null || trim((string) $lotNumber) === '') {
                            $validator->errors()->add('lot_number', __('Debes indicar el número de lote para crear un lote nuevo.'));
                        }
                    }

                    return;
                }

                $batch = InventoryBatch::query()->find((int) $batchId);
                if ($batch === null) {
                    return;
                }

                if ((int) $batch->raw_material_id !== (int) $this->input('raw_material_id')) {
                    $validator->errors()->add('batch_id', __('El lote seleccionado no pertenece a la materia prima indicada.'));
                }

                if ((int) $batch->warehouse_id !== (int) $this->input('warehouse_id')) {
                    $validator->errors()->add('batch_id', __('El lote seleccionado no pertenece a la bodega indicada.'));
                }
            },
        ];
    }
}
