import { usePage } from '@inertiajs/react';
import type { useForm } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

import { formatSafeDate } from '@/components/formatted-date';
import { IntegerInput } from '@/components/integer-input';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useEnumCase } from '@/hooks/use-enum-case';
import { finishedLotLabel } from '@/lib/finished-lot';
import { formatNumber } from '@/lib/formatters';
import { UNITS_MAX_DIGITS } from '@/lib/number-types';
import type {
    FinishedBatchOption,
    FinishedMovementReason,
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
    reasons?: Array<{ value: FinishedMovementReason; label: string }>;
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
    const { plantToday } = usePage().props;
    const transferReason = useEnumCase(
        'FinishedInventoryMovementReason',
        'transfer',
    );
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

    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <div className="space-y-2">
                <Label htmlFor="warehouse_id">
                    {showDestinationWarehouse ? 'Bodega origen' : 'Bodega'}
                </Label>
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
                    <SelectTrigger id="warehouse_id">
                        <SelectValue placeholder="Selecciona bodega" />
                    </SelectTrigger>
                    <SelectContent>
                        {warehouses.map((warehouse) => (
                            <SelectItem
                                key={warehouse.id}
                                value={String(warehouse.id)}
                            >
                                {warehouse.name}
                                {warehouse.city ? ` (${warehouse.city})` : ''}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                {form.errors.warehouse_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.warehouse_id}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="finished_product_key">Producto</Label>
                <Combobox
                    id="finished_product_key"
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
            </div>

            <div className="space-y-2">
                <Label htmlFor="finished_variant_key">Presentación</Label>
                <Select
                    value={activeVariantKey}
                    onValueChange={(value) => {
                        setSelectedVariantKey(value);
                        form.setData('finished_product_batch_id', '');
                    }}
                    disabled={variantOptions.length === 0}
                >
                    <SelectTrigger id="finished_variant_key">
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
            </div>

            <div className="space-y-2">
                <Label htmlFor="finished_product_batch_id">Lote PT</Label>
                <Combobox
                    id="finished_product_batch_id"
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
                {form.errors.finished_product_batch_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.finished_product_batch_id}
                    </p>
                )}
            </div>

            {showDestinationWarehouse && (
                <div className="space-y-2">
                    <Label htmlFor="destination_warehouse_id">
                        Bodega destino
                    </Label>
                    <Select
                        value={form.data.destination_warehouse_id ?? ''}
                        onValueChange={(value) =>
                            form.setData('destination_warehouse_id', value)
                        }
                    >
                        <SelectTrigger id="destination_warehouse_id">
                            <SelectValue placeholder="Selecciona destino" />
                        </SelectTrigger>
                        <SelectContent>
                            {destinationWarehouses.map((warehouse) => (
                                <SelectItem
                                    key={warehouse.id}
                                    value={String(warehouse.id)}
                                >
                                    {warehouse.name}
                                    {warehouse.city
                                        ? ` (${warehouse.city})`
                                        : ''}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.destination_warehouse_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.destination_warehouse_id}
                        </p>
                    )}
                </div>
            )}

            {reasons && (
                <div className="space-y-2">
                    <Label htmlFor="reason">Razón</Label>
                    <Select
                        value={form.data.reason}
                        onValueChange={(value) =>
                            form.setData(
                                'reason',
                                value as FinishedMovementReason,
                            )
                        }
                    >
                        <SelectTrigger id="reason">
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
                    {form.errors.reason && (
                        <p className="text-sm text-destructive">
                            {form.errors.reason}
                        </p>
                    )}
                </div>
            )}

            {showDestinationWarehouse && (
                <div className="space-y-2">
                    <Label htmlFor="reason">Razón</Label>
                    <Input
                        id="reason"
                        value={transferReason?.label ?? ''}
                        disabled
                        className="bg-muted text-muted-foreground"
                    />
                </div>
            )}

            <div className="space-y-2">
                <Label htmlFor="quantity">Cantidad</Label>
                <IntegerInput
                    id="quantity"
                    maxDigits={UNITS_MAX_DIGITS}
                    value={form.data.quantity}
                    onValueChange={(value) => form.setData('quantity', value)}
                    placeholder="0"
                />
                {form.errors.quantity && (
                    <p className="text-sm text-destructive">
                        {form.errors.quantity}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="movement_date">Fecha</Label>
                <Input
                    id="movement_date"
                    type="date"
                    max={plantToday}
                    value={form.data.movement_date}
                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                        form.setData('movement_date', event.target.value)
                    }
                />
                {form.errors.movement_date && (
                    <p className="text-sm text-destructive">
                        {form.errors.movement_date}
                    </p>
                )}
            </div>

            <div className="space-y-2 md:col-span-2">
                <Label htmlFor="notes">Notas</Label>
                <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(event: ChangeEvent<HTMLTextAreaElement>) =>
                        form.setData('notes', event.target.value)
                    }
                    placeholder="Observaciones del movimiento..."
                    className="min-h-24"
                />
                {form.errors.notes && (
                    <p className="text-sm text-destructive">
                        {form.errors.notes}
                    </p>
                )}
            </div>
        </div>
    );
}
