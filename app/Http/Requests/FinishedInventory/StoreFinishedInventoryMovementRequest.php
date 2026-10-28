<?php

declare(strict_types=1);

namespace App\Http\Requests\FinishedInventory;

use App\Enums\FinishedInventoryMovementReason;
use App\Enums\InventoryMovementType;
use App\Enums\NumberType;
use App\Http\Requests\Concerns\ReadsIdInput;
use App\Models\FinishedInventoryMovement;
use App\Models\FinishedProductBatch;
use App\Rules\PlantDate;
use App\Services\TimezoneService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFinishedInventoryMovementRequest extends FormRequest
{
    use ReadsIdInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', FinishedInventoryMovement::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'finished_product_batch_id' => [
                'bail',
                'required',
                'integer',
                Rule::exists('finished_product_batches', 'id'),
            ],
            'warehouse_id' => ['bail', 'required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'type' => [
                'bail',
                Rule::requiredIf(fn () => $this->input('reason') !== FinishedInventoryMovementReason::Transfer->value),
                Rule::in(['entry', 'exit']),
            ],
            'reason' => [
                'bail',
                'required',
                Rule::in(array_column(FinishedInventoryMovementReason::cases(), 'value')),
            ],
            // El PT se cuenta en unidades enteras de su presentación (P4-1 de la auditoría 3.6).
            'quantity' => ['bail', 'required', ...NumberType::Units->rules(), 'min:1'],
            'movement_date' => ['bail', 'required', PlantDate::make()->notFuture()],
            'destination_warehouse_id' => ['nullable', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'production_order_id' => ['prohibited'],
            'created_by' => ['prohibited'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [
            function ($validator): void {
                $this->validateReasonForType($validator);
                $this->validateTransferFields($validator);
                $this->validateBatchBelongsToWarehouse($validator);
                $this->validateEntryIsForActiveProduct($validator);
                $this->validateDateNotBeforeBatchEntry($validator);
            },
        ];
    }

    private function validateReasonForType(Validator $validator): void
    {
        // Un arreglo ya lo rechazan `in` y `required`; aquí solo se compara lo que sí es texto.
        $type = is_string($this->input('type')) ? InventoryMovementType::tryFrom($this->input('type')) : null;
        $reason = is_string($this->input('reason')) ? FinishedInventoryMovementReason::tryFrom($this->input('reason')) : null;

        // Un traslado no tiene tipo propio: el formulario lo envía como salida y el controlador lo trata como traslado
        // sea cual sea el tipo. Su destino y su stock los revisan `validateTransferFields` y el servicio.
        if ($type === null || $reason === null || $reason === FinishedInventoryMovementReason::Transfer) {
            return;
        }

        $validReasons = FinishedInventoryMovementReason::manualReasonsFor($type);

        if (! in_array($reason, $validReasons, true)) {
            $validator->errors()->add('reason', __('La razón seleccionada no es válida para este tipo de movimiento.'));
        }
    }

    private function validateTransferFields(Validator $validator): void
    {
        $reason = $this->input('reason');

        if ($reason !== FinishedInventoryMovementReason::Transfer->value) {
            return;
        }

        $destinationWarehouseId = $this->input('destination_warehouse_id');

        if ($destinationWarehouseId === null || $destinationWarehouseId === '') {
            $validator->errors()->add('destination_warehouse_id', __('Debe seleccionar la bodega destino para un traslado.'));

            return;
        }

        if ((int) $destinationWarehouseId === (int) $this->input('warehouse_id')) {
            $validator->errors()->add('destination_warehouse_id', __('La bodega destino debe ser diferente a la bodega origen.'));
        }
    }

    private function validateBatchBelongsToWarehouse(Validator $validator): void
    {
        $batchId = $this->input('finished_product_batch_id');
        $type = $this->input('type');

        if ($batchId === null || $type !== 'exit') {
            return;
        }

        $batch = FinishedProductBatch::query()
            ->with(['stocks' => fn ($q) => $q->where('warehouse_id', $this->idInput('warehouse_id'))->available()])
            ->find((int) $batchId);

        if ($batch === null) {
            return;
        }

        if ($batch->stocks->isEmpty()) {
            $validator->errors()->add('finished_product_batch_id', __('El lote seleccionado no tiene stock disponible en la bodega indicada.'));
        }
    }

    /**
     * Ningún movimiento (entrada, salida o traslado) puede fecharse antes de que su lote entrara: el kardex lo mostraría
     * antes de la entrada, como en materia prima. El `entry_date` del lote no cambia, así que no hace falta bloquearlo
     * aquí. Solo se compara una fecha ya válida.
     */
    private function validateDateNotBeforeBatchEntry(Validator $validator): void
    {
        $batchId = $this->input('finished_product_batch_id');
        $movementDate = $this->input('movement_date');

        if ($validator->errors()->hasAny(['finished_product_batch_id', 'movement_date']) || ! is_string($movementDate)) {
            return;
        }

        $batch = FinishedProductBatch::query()->find((int) $batchId);

        if ($batch === null) {
            return;
        }

        $entryDate = $batch->entry_date->format('Y-m-d');

        if (strcmp($movementDate, $entryDate) < 0) {
            $validator->errors()->add('movement_date', __('La fecha de movimiento no puede ser anterior a la entrada del lote (:date).', [
                'date' => app(TimezoneService::class)->formatPlantDate($entryDate),
            ]));
        }
    }

    /**
     * Una entrada no puede sumar stock a un producto o presentación inactivos: ningún pedido lo ofrecería. Las salidas y
     * los traslados sí se permiten, para poder dar salida a lo que quede (docs/POLITICA_ELIMINACION.md §3.1).
     */
    private function validateEntryIsForActiveProduct(Validator $validator): void
    {
        $batchId = $this->input('finished_product_batch_id');

        if ($batchId === null || $this->input('type') !== InventoryMovementType::Entry->value) {
            return;
        }

        $batch = FinishedProductBatch::query()
            ->with(['product:id,is_active', 'productVariant:id,is_active'])
            ->find((int) $batchId);

        if ($batch === null) {
            return;
        }

        if ($batch->product?->is_active === false || $batch->productVariant?->is_active === false) {
            $validator->errors()->add('finished_product_batch_id', __('No se puede ingresar stock a un producto o presentación inactivos.'));
        }
    }
}
