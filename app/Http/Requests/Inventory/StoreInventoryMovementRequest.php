<?php

declare(strict_types=1);

namespace App\Http\Requests\Inventory;

use App\Enums\InventoryMovementType;
use App\Enums\NumberType;
use App\Enums\WarehouseType;
use App\Http\Requests\Concerns\ValidatesBatchEntryDate;
use App\Models\InventoryBatch;
use App\Models\InventoryMovement;
use App\Models\RawMaterial;
use App\Models\Warehouse;
use App\Rules\PlantDate;
use App\Services\InventoryService;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\ExcludeIf;

class StoreInventoryMovementRequest extends FormRequest
{
    use ValidatesBatchEntryDate;

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
            // Solo la fábrica recibe materia prima (D6, INV-06): los almacenes reciben producto terminado por Movimientos de PT.
            'warehouse_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('warehouses', 'id')->where('is_active', true),
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (Warehouse::query()->whereKey((int) $value)->where('type', '!=', WarehouseType::Factory->value)->exists()) {
                        $fail(__('La materia prima solo se registra en la fábrica. Los almacenes reciben producto terminado.'));
                    }
                },
            ],
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
            // Lote, proveedor y vencimiento solo se usan al crear un lote nuevo (una entrada sin lote elegido). En una salida o
            // una entrada a un lote existente el servicio los ignora, así que tampoco se validan (INV-13).
            'lot_number' => [$this->excludeUnlessNewLot(), 'nullable', 'string', 'max:50'],
            'supplier' => [$this->excludeUnlessNewLot(), 'nullable', 'string', 'max:150'],
            'expiry_date' => [$this->excludeUnlessNewLot(), 'bail', 'nullable', PlantDate::make(), 'after_or_equal:movement_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'created_by' => ['prohibited'],
        ];
    }

    private function excludeUnlessNewLot(): ExcludeIf
    {
        return Rule::excludeIf(fn (): bool => $this->input('type') !== InventoryMovementType::Entry->value || filled($this->input('batch_id')));
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

                        // Un arreglo o un número ya los rechaza la regla `string`: aquí solo se revisa que haya texto.
                        $missing = $lotNumber === null || (is_string($lotNumber) && trim($lotNumber) === '');

                        if ($missing) {
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

                // Una salida no puede fecharse antes de que su lote entrara (una entrada a un lote nuevo lo crea con su fecha).
                if ($this->input('type') === InventoryMovementType::Exit->value) {
                    $this->validateMovementNotBeforeBatchEntry($validator, $batch->entry_date);
                }
            },
        ];
    }
}
