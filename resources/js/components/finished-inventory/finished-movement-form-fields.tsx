import type { useForm } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

import { formatSafeDate } from '@/components/formatted-date';
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
import { formatNumber } from '@/lib/formatters';
import { finishedMovementReasonLabels } from '@/types/finished-inventory';
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
    const productCode = batch.product?.code ?? 'PT';
    const variantLabel = variantLabelFor(batch);

    return `${productCode} · ${variantLabel} · Lote #${batch.id}`;
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

function productOptionKey(batch: FinishedBatchOption): string {
    return `${batch.product?.id ?? batch.product_id ?? 'product'}:${batch.product_variant?.id ?? batch.variant?.id ?? batch.product_variant_id ?? 'base'}`;
}

function productLabel(batch: FinishedBatchOption): string {
    const productCode = batch.product?.code ?? 'PT';

    return `${productCode} · ${variantLabelFor(batch)}`;
}

function productDescription(batch: FinishedBatchOption): string {
    return batch.product?.name ?? 'Producto terminado';
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
    const productOptions = useMemo(() => {
        const options = new Map<
            string,
            { id: string; label: string; description: string }
        >();

        warehouseFilteredBatches.forEach((batch) => {
            const id = productOptionKey(batch);

            if (!options.has(id)) {
                options.set(id, {
                    id,
                    label: productLabel(batch),
                    description: productDescription(batch),
                });
            }
        });

        return Array.from(options.values());
    }, [warehouseFilteredBatches]);
    const isSelectedProductAvailable =
        selectedProductKey === '' ||
        productOptions.some((option) => option.id === selectedProductKey);
    const activeProductKey = isSelectedProductAvailable
        ? selectedProductKey
        : '';
    const filteredBatches = useMemo(
        () =>
            activeProductKey
                ? warehouseFilteredBatches.filter(
                      (batch) => productOptionKey(batch) === activeProductKey,
                  )
                : [],
        [activeProductKey, warehouseFilteredBatches],
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
                <Label htmlFor="finished_product_key">
                    Producto / presentación
                </Label>
                <Combobox
                    options={productOptions}
                    value={activeProductKey}
                    onChange={(value) => {
                        setSelectedProductKey(String(value));
                        form.setData('finished_product_batch_id', '');
                    }}
                    placeholder={
                        shouldRequireWarehouseStock &&
                        selectedOriginWarehouse === null
                            ? 'Selecciona bodega primero'
                            : 'Busca producto o presentación...'
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

            <div className="space-y-2 md:col-span-2">
                <Label htmlFor="finished_product_batch_id">Lote PT</Label>
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
                        activeProductKey
                            ? 'Busca o selecciona lote...'
                            : 'Selecciona producto primero'
                    }
                    emptyText="No hay lotes disponibles"
                    disabled={!activeProductKey}
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
                        value={finishedMovementReasonLabels.transfer}
                        disabled
                        className="bg-muted text-muted-foreground"
                    />
                </div>
            )}

            <div className="space-y-2">
                <Label htmlFor="quantity">Cantidad</Label>
                <Input
                    id="quantity"
                    type="number"
                    step="0.0001"
                    value={form.data.quantity}
                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                        form.setData('quantity', event.target.value)
                    }
                    placeholder="0.0000"
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
