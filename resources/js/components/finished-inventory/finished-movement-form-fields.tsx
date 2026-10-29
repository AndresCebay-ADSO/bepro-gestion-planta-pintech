import type { useForm } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

import { FormField } from '@/components/form-field';
import { formatSafeDate } from '@/components/formatted-date';
import { IntegerInput } from '@/components/integer-input';
import { PlantDateInput } from '@/components/plant-date-input';
import { Combobox } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useSelectionDateMin } from '@/hooks/use-selection-date-min';
import { finishedLotLabel } from '@/lib/finished-lot';
import { formatNumber } from '@/lib/formatters';
import { UNITS_MAX_DIGITS } from '@/lib/number-types';
import type {
    FinishedBatchOption,
    FinishedMovementReason,
    FinishedReasonOption,
    FinishedWarehouseOption,
} from '@/types/finished-inventory';

export type FinishedMovementFormData = {
    finished_product_batch_id: string;
    warehouse_id: string;
    destination_warehouse_id?: string;
    type?: 'entry' | 'exit';
    reason: FinishedMovementReason;
    quantity: string;
    movement_date: string;
    notes: string;
};

type Props = {
    form: ReturnType<typeof useForm<FinishedMovementFormData>>;
    batches: FinishedBatchOption[];
    warehouses: FinishedWarehouseOption[];
    reasons?: FinishedReasonOption[];
    showDestinationWarehouse?: boolean;
};

function batchLabel(batch: FinishedBatchOption): string {
    const productName = batch.product?.name ?? 'PT';
    const variantLabel = variantLabelFor(batch);

    return `${productName} · ${variantLabel} · Lote ${finishedLotLabel(batch.lot_number, batch.color)}`;
}

function variantLabelFor(batch: FinishedBatchOption): string {
    return (
        batch.product_variant?.presentation_label ??
        batch.variant?.presentation_label ??
        batch.product_variant?.name ??
        batch.variant?.name ??
        'Presentación'
    );
}

function productKeyFor(batch: FinishedBatchOption): string {
    return String(batch.product?.id ?? batch.product_id ?? 'product');
}

/** Producto y presentación: dos lotes de la misma presentación comparten esta clave. */
function variantKeyFor(batch: FinishedBatchOption): string {
    return `${productKeyFor(batch)}:${batch.product_variant?.id ?? batch.variant?.id ?? batch.product_variant_id ?? 'base'}`;
}

/** Opciones únicas por clave, en el orden en que aparecen los lotes. */
function uniqueOptions(
    batches: FinishedBatchOption[],
    keyFor: (batch: FinishedBatchOption) => string,
    labelFor: (batch: FinishedBatchOption) => string,
): Array<{ id: string; label: string }> {
    const options = new Map<string, { id: string; label: string }>();

    batches.forEach((batch) => {
        const id = keyFor(batch);

        if (!options.has(id)) {
            options.set(id, { id, label: labelFor(batch) });
        }
    });

    return Array.from(options.values());
}

function batchStockInWarehouse(
    batch: FinishedBatchOption,
    warehouseId: number,
): number {
    return (
        batch.stocks
            ?.filter((stock) => stock.warehouse_id === warehouseId)
            .reduce((total, stock) => total + Number(stock.quantity), 0) ?? 0
    );
}

function batchHasStockInWarehouse(
    batch: FinishedBatchOption,
    warehouseId: number,
): boolean {
    return batchStockInWarehouse(batch, warehouseId) > 0;
}

function batchDescription(
    batch: FinishedBatchOption,
    selectedWarehouseId: number | null,
): string {
    const selectedWarehouseStock = selectedWarehouseId
        ? batchStockInWarehouse(batch, selectedWarehouseId)
        : null;
    const stockText =
        selectedWarehouseStock !== null
            ? formatNumber(selectedWarehouseStock, { maxDecimals: 2 })
            : batch.stocks && batch.stocks.length > 0
              ? batch.stocks
                    .map((stock) =>
                        formatNumber(stock.quantity, { maxDecimals: 2 }),
                    )
                    .join(' / ')
              : formatNumber(batch.initial_quantity, { maxDecimals: 2 });

    const entryDate = formatSafeDate(batch.entry_date, 'short', '-');

    return `Ingreso ${entryDate} · Disp. ${stockText}`;
}

export function FinishedMovementFormFields({
    form,
    batches,
    warehouses,
    reasons,
    showDestinationWarehouse = false,
}: Props) {
    const selectedOriginWarehouseId = Number(form.data.warehouse_id);
    const selectedOriginWarehouse =
        Number.isFinite(selectedOriginWarehouseId) &&
        selectedOriginWarehouseId > 0
            ? selectedOriginWarehouseId
            : null;
    const shouldRequireWarehouseStock = form.data.type === 'exit';
    const [selectedProductKey, setSelectedProductKey] = useState('');
    const [selectedVariantKey, setSelectedVariantKey] = useState('');
    const destinationWarehouses = warehouses.filter(
        (warehouse) => warehouse.id !== selectedOriginWarehouseId,
    );
    const warehouseFilteredBatches = useMemo(
        () =>
            shouldRequireWarehouseStock
                ? batches.filter(
                      (batch) =>
                          selectedOriginWarehouse !== null &&
                          batchHasStockInWarehouse(
                              batch,
                              selectedOriginWarehouse,
                          ),
                  )
                : batches,
        [batches, selectedOriginWarehouse, shouldRequireWarehouseStock],
    );
    // Producto → presentación → lote, como en la cotización y el pedido.
    const productOptions = useMemo(
        () =>
            uniqueOptions(
                warehouseFilteredBatches,
                productKeyFor,
                (batch) => batch.product?.name ?? 'Producto terminado',
            ),
        [warehouseFilteredBatches],
    );
    const activeProductKey = productOptions.some(
        (option) => option.id === selectedProductKey,
    )
        ? selectedProductKey
        : '';
    const variantOptions = useMemo(
        () =>
            activeProductKey
                ? uniqueOptions(
                      warehouseFilteredBatches.filter(
                          (batch) => productKeyFor(batch) === activeProductKey,
                      ),
                      variantKeyFor,
                      variantLabelFor,
                  )
                : [],
        [activeProductKey, warehouseFilteredBatches],
    );
    // Con una sola presentación disponible no hay nada que elegir.
    const activeVariantKey =
        variantOptions.length === 1
            ? variantOptions[0].id
            : variantOptions.some((option) => option.id === selectedVariantKey)
              ? selectedVariantKey
              : '';
    const filteredBatches = useMemo(
        () =>
            activeVariantKey
                ? warehouseFilteredBatches.filter(
                      (batch) => variantKeyFor(batch) === activeVariantKey,
                  )
                : [],
        [activeVariantKey, warehouseFilteredBatches],
    );
    const selectedBatchId = form.data.finished_product_batch_id;
    const setFormData = form.setData;
    // El servidor rechaza un movimiento fechado antes de la entrada del lote: el calendario no ofrece esos días.
    const selectedBatch = selectedBatchId
        ? filteredBatches.find(
              (batch) => String(batch.id) === String(selectedBatchId),
          )
        : undefined;
    const movementDateMin = useSelectionDateMin({
        selectedDate: selectedBatch?.entry_date,
        value: form.data.movement_date,
        clear: () => setFormData('movement_date', ''),
    });

    useEffect(() => {
        if (
            selectedBatchId &&
            !filteredBatches.some(
                (batch) => String(batch.id) === String(selectedBatchId),
            )
        ) {
            setFormData('finished_product_batch_id', '');
        }
    }, [filteredBatches, selectedBatchId, setFormData]);

    const warehouseItems = (list: FinishedWarehouseOption[]) =>
        list.map((warehouse) => (
            <SelectItem key={warehouse.id} value={String(warehouse.id)}>
                {warehouse.name}
                {warehouse.city ? ` (${warehouse.city})` : ''}
            </SelectItem>
        ));

    // Orden del razonamiento: qué registro es (razón), dónde (bodega), qué (producto → presentación → lote), cuánto,
    // cuándo y notas. En dos columnas, por pares; el lote va a lo ancho porque su texto es largo.
    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            {reasons && (
                <FormField id="reason" label="Razón" error={form.errors.reason}>
                    {(control) => (
                        <Select
                            value={form.data.reason}
                            onValueChange={(value) =>
                                form.setData(
                                    'reason',
                                    value as FinishedMovementReason,
                                )
                            }
                        >
                            <SelectTrigger {...control}>
                                <SelectValue placeholder="Selecciona razón" />
                            </SelectTrigger>
                            <SelectContent>
                                {reasons.map((reason) => (
                                    <SelectItem
                                        key={reason.value}
                                        value={reason.value}
                                    >
                                        {reason.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}
                </FormField>
            )}

            <FormField
                id="warehouse_id"
                label={showDestinationWarehouse ? 'Bodega origen' : 'Bodega'}
                error={form.errors.warehouse_id}
            >
                {(control) => (
                    <Select
                        value={form.data.warehouse_id}
                        onValueChange={(value) => {
                            form.setData('warehouse_id', value);
                            form.setData('finished_product_batch_id', '');
                            setSelectedProductKey('');
                            setSelectedVariantKey('');

                            if (form.data.destination_warehouse_id === value) {
                                form.setData('destination_warehouse_id', '');
                            }
                        }}
                    >
                        <SelectTrigger {...control}>
                            <SelectValue placeholder="Selecciona bodega" />
                        </SelectTrigger>
                        <SelectContent>
                            {warehouseItems(warehouses)}
                        </SelectContent>
                    </Select>
                )}
            </FormField>

            {showDestinationWarehouse && (
                <FormField
                    id="destination_warehouse_id"
                    label="Bodega destino"
                    error={form.errors.destination_warehouse_id}
                >
                    {(control) => (
                        <Select
                            value={form.data.destination_warehouse_id ?? ''}
                            onValueChange={(value) =>
                                form.setData('destination_warehouse_id', value)
                            }
                        >
                            <SelectTrigger {...control}>
                                <SelectValue placeholder="Selecciona destino" />
                            </SelectTrigger>
                            <SelectContent>
                                {warehouseItems(destinationWarehouses)}
                            </SelectContent>
                        </Select>
                    )}
                </FormField>
            )}

            <FormField id="finished_product_key" label="Producto">
                <Combobox
                    options={productOptions}
                    value={activeProductKey}
                    onChange={(value) => {
                        setSelectedProductKey(String(value));
                        setSelectedVariantKey('');
                        form.setData('finished_product_batch_id', '');
                    }}
                    placeholder={
                        shouldRequireWarehouseStock &&
                        selectedOriginWarehouse === null
                            ? 'Selecciona bodega primero'
                            : 'Busca producto...'
                    }
                    emptyText={
                        shouldRequireWarehouseStock
                            ? 'No hay producto disponible en esta bodega'
                            : 'No hay productos disponibles'
                    }
                    disabled={
                        shouldRequireWarehouseStock &&
                        selectedOriginWarehouse === null
                    }
                />
            </FormField>

            <FormField id="finished_variant_key" label="Presentación">
                {(control) => (
                    <Select
                        value={activeVariantKey}
                        onValueChange={(value) => {
                            setSelectedVariantKey(value);
                            form.setData('finished_product_batch_id', '');
                        }}
                        disabled={variantOptions.length === 0}
                    >
                        <SelectTrigger {...control}>
                            <SelectValue
                                placeholder={
                                    activeProductKey
                                        ? 'Selecciona presentación'
                                        : 'Selecciona producto primero'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {variantOptions.map((option) => (
                                <SelectItem key={option.id} value={option.id}>
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </FormField>

            <FormField
                id="finished_product_batch_id"
                label="Lote PT"
                error={form.errors.finished_product_batch_id}
                className="md:col-span-2"
            >
                <Combobox
                    options={filteredBatches.map((batch) => ({
                        id: String(batch.id),
                        label: batchLabel(batch),
                        description: batchDescription(
                            batch,
                            selectedOriginWarehouse,
                        ),
                    }))}
                    value={form.data.finished_product_batch_id}
                    onChange={(value) =>
                        form.setData('finished_product_batch_id', String(value))
                    }
                    placeholder={
                        activeVariantKey
                            ? 'Busca o selecciona lote...'
                            : 'Selecciona presentación primero'
                    }
                    emptyText="No hay lotes disponibles"
                    disabled={!activeVariantKey}
                />
            </FormField>

            <FormField
                id="quantity"
                label="Cantidad"
                error={form.errors.quantity}
            >
                <IntegerInput
                    maxDigits={UNITS_MAX_DIGITS}
                    value={form.data.quantity}
                    onValueChange={(value) => form.setData('quantity', value)}
                    placeholder="0"
                />
            </FormField>

            <FormField
                id="movement_date"
                label="Fecha"
                error={form.errors.movement_date}
            >
                <PlantDateInput
                    notFuture
                    min={movementDateMin}
                    value={form.data.movement_date}
                    onChange={(value) => form.setData('movement_date', value)}
                />
            </FormField>

            <FormField
                id="notes"
                label="Notas"
                error={form.errors.notes}
                className="md:col-span-2"
            >
                <Textarea
                    value={form.data.notes}
                    onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
                        form.setData('notes', event.target.value)
                    }
                    placeholder="Observaciones del movimiento..."
                    className="min-h-24"
                />
            </FormField>
        </div>
    );
}
