import type { useForm } from '@inertiajs/react';
import type { ChangeEvent, ReactNode } from 'react';
import { useMemo } from 'react';
import { DecimalInput } from '@/components/decimal-input';
import { FormField } from '@/components/form-field';
import { formatSafeDate } from '@/components/formatted-date';
import { PlantDateInput } from '@/components/plant-date-input';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useSelectionDateMin } from '@/hooks/use-selection-date-min';
import { formatForInput, formatNumber } from '@/lib/formatters';
import type { InventoryOption } from '@/types';

type Props = {
    form: ReturnType<typeof useForm<any>>;
    rawMaterials: InventoryOption[];
    batches: InventoryOption[];
    warehouses: InventoryOption[];
    type: 'entry' | 'exit';
    /**
     * Límite propio del formulario para la fecha (la entrada: no más de un mes atrás, D1b). En una salida, el lote elegido
     * lo sube a su entrada.
     */
    movementDateMin?: string;
    /** Junto a la fecha del movimiento, en su misma fila (el precio de una entrada). */
    beforeDate?: ReactNode;
    /** Después de la fecha (los datos de un lote nuevo). */
    children?: ReactNode;
};

/** Valor de la opción «Registrar como nuevo lote» (en el formulario, `batch_id` queda vacío). */
const NEW_BATCH = '__new__';

function batchDescription(batch: InventoryOption): string {
    return [
        // Con la misma precisión que valida el servidor: 9,9999 no se redondea a 10 (INV-05).
        `Disponible ${formatNumber(batch.remaining_quantity, {
            maxDecimals: 4,
            trimTrailingZeros: true,
        })}`,
        batch.entry_date
            ? `entró ${formatSafeDate(batch.entry_date, 'date')}`
            : null,
        batch.expiry_date
            ? `vence ${formatSafeDate(batch.expiry_date, 'date')}`
            : null,
    ]
        .filter(Boolean)
        .join(' · ');
}

export function MovementFormBase({
    form,
    rawMaterials,
    batches,
    warehouses,
    type,
    movementDateMin,
    beforeDate,
    children,
}: Props) {
    const filteredBatches = useMemo(() => {
        if (!form.data.raw_material_id) {
            return [];
        }

        return batches.filter(
            (b) =>
                Number(b.raw_material_id) ===
                    Number(form.data.raw_material_id) &&
                Number(b.warehouse_id) === Number(form.data.warehouse_id),
        );
    }, [batches, form.data.raw_material_id, form.data.warehouse_id]);

    // En una entrada, la primera opción es registrar un lote nuevo; cada lote dice cuánto queda, cuándo entró y vence.
    const batchOptions = useMemo(
        () => [
            ...(type === 'entry'
                ? [{ id: NEW_BATCH, label: 'Registrar como nuevo lote' }]
                : []),
            ...filteredBatches.map((b) => ({
                id: String(b.id),
                label: b.lot_number ?? `Auto #${b.id}`,
                description: batchDescription(b),
            })),
        ],
        [filteredBatches, type],
    );

    // Una salida no puede fecharse antes de que su lote entrara (lo valida el servidor). En una entrada no aplica: un lote
    // nuevo se crea con la fecha del movimiento.
    const exitBatch =
        type === 'exit' && form.data.batch_id
            ? filteredBatches.find((b) => String(b.id) === form.data.batch_id)
            : undefined;
    const movementDateFloor = useSelectionDateMin({
        selectedDate: exitBatch?.entry_date,
        min: movementDateMin,
        value: form.data.movement_date,
        clear: () => form.setData('movement_date', ''),
    });

    const selectBatch = (value: string) => {
        const batchId = value === NEW_BATCH ? '' : value;

        if (type !== 'entry') {
            form.setData('batch_id', batchId);

            return;
        }

        const selectedBatch = filteredBatches.find(
            (b) => String(b.id) === batchId,
        );

        form.setData((prev: any) => ({
            ...prev,
            batch_id: batchId,
            cost_price:
                selectedBatch?.unit_price !== undefined &&
                selectedBatch?.unit_price !== null &&
                selectedBatch?.unit_price !== ''
                    ? // Sin los ceros del decimal(16,4): el campo admite 2 decimales.
                      formatForInput(selectedBatch.unit_price)
                    : prev.batch_id !== batchId
                      ? ''
                      : prev.cost_price,
        }));
    };

    // En dos columnas, por pares: bodega y MP, lote y cantidad, (precio y) fecha; lo que sigue lo pone la entrada.
    return (
        <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
            <FormField
                id="warehouse_id"
                label="Bodega"
                error={form.errors.warehouse_id}
            >
                {(control) => (
                    <Select
                        value={form.data.warehouse_id}
                        onValueChange={(value) => {
                            form.setData((prev: any) => ({
                                ...prev,
                                warehouse_id: value,
                                batch_id: '',
                                cost_price:
                                    type === 'entry' && prev.batch_id !== ''
                                        ? ''
                                        : prev.cost_price,
                            }));
                        }}
                    >
                        <SelectTrigger {...control}>
                            <SelectValue placeholder="Selecciona la bodega" />
                        </SelectTrigger>
                        <SelectContent>
                            {warehouses.map((w) => (
                                <SelectItem key={w.id} value={String(w.id)}>
                                    {w.name} ({w.city})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
            </FormField>

            <FormField
                id="raw_material_id"
                label="Materia prima"
                error={form.errors.raw_material_id}
            >
                <Combobox
                    options={rawMaterials.map((rm) => ({
                        id: String(rm.id),
                        label: rm.code ?? `MP #${rm.id}`,
                        description: rm.name,
                    }))}
                    value={form.data.raw_material_id}
                    onChange={(value) => {
                        form.setData((prev: any) => ({
                            ...prev,
                            raw_material_id: String(value),
                            batch_id: '',
                            cost_price:
                                type === 'entry' && prev.batch_id !== ''
                                    ? ''
                                    : prev.cost_price,
                        }));
                    }}
                    placeholder="Busca o selecciona materia prima..."
                    emptyText="No se encontraron materias primas"
                />
            </FormField>

            {type === 'exit' ||
            (type === 'entry' && filteredBatches.length > 0) ? (
                <FormField
                    id="batch_id"
                    label={type === 'entry' ? 'Lote (opcional)' : 'Lote'}
                    error={form.errors.batch_id}
                >
                    <Combobox
                        options={batchOptions}
                        value={
                            form.data.batch_id === '' && type === 'entry'
                                ? NEW_BATCH
                                : form.data.batch_id
                        }
                        onChange={(value) => selectBatch(String(value))}
                        disabled={!form.data.raw_material_id}
                        placeholder={
                            form.data.raw_material_id
                                ? type === 'exit' &&
                                  filteredBatches.length === 0
                                    ? 'No hay lotes disponibles'
                                    : 'Busca o selecciona lote...'
                                : 'Selecciona MP primero'
                        }
                        emptyText="No se encontraron lotes"
                    />
                </FormField>
            ) : (
                <FormField id="batch_id" label="Lote">
                    <Input
                        value="Se generará uno nuevo"
                        disabled
                        className="bg-muted text-muted-foreground"
                    />
                </FormField>
            )}

            <FormField
                id="quantity"
                label="Cantidad"
                error={form.errors.quantity}
            >
                <DecimalInput
                    value={form.data.quantity}
                    onValueChange={(value) => form.setData('quantity', value)}
                    placeholder="0,00"
                />
            </FormField>

            {beforeDate}

            <FormField
                id="movement_date"
                label="Fecha del movimiento"
                error={form.errors.movement_date}
                // Sin un campo al lado (salida), la fecha ocupa la fila y no queda sola a media fila.
                className={beforeDate ? undefined : 'md:col-span-2'}
            >
                <PlantDateInput
                    notFuture
                    min={movementDateFloor}
                    value={form.data.movement_date}
                    onChange={(value) => form.setData('movement_date', value)}
                />
            </FormField>

            {children}

            <FormField
                id="notes"
                label="Notas"
                error={form.errors.notes}
                className="md:col-span-2"
            >
                <Textarea
                    maxLength={2000}
                    value={form.data.notes}
                    onChange={(e: ChangeEvent<HTMLTextAreaElement>) =>
                        form.setData('notes', e.target.value)
                    }
                    placeholder={
                        type === 'entry'
                            ? 'Detalles adicionales de la entrada...'
                            : 'Motivo de la salida (ej: merma, consumo producción...)'
                    }
                    className="min-h-20"
                />
            </FormField>
        </div>
    );
}
