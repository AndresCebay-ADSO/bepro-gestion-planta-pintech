import type { useForm } from '@inertiajs/react';
import type { ChangeEvent, ReactNode } from 'react';
import { useMemo } from 'react';
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
import type { InventoryOption } from '@/types';

type Props = {
    form: ReturnType<typeof useForm<any>>;
    rawMaterials: InventoryOption[];
    batches: InventoryOption[];
    warehouses: InventoryOption[];
    type: 'entry' | 'exit';
    children?: ReactNode;
};

export function MovementFormBase({
    form,
    rawMaterials,
    batches,
    warehouses,
    type,
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

    return (
        <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div className="space-y-2">
                <Label htmlFor="warehouse_id">Bodega</Label>
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
                    <SelectTrigger id="warehouse_id">
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
                {form.errors.warehouse_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.warehouse_id}
                    </p>
                )}
            </div>

            <div className="space-y-2">
                <Label htmlFor="raw_material_id">Materia Prima</Label>
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
                {form.errors.raw_material_id && (
                    <p className="text-sm text-destructive">
                        {form.errors.raw_material_id}
                    </p>
                )}
            </div>

            {type === 'exit' ||
            (type === 'entry' && filteredBatches.length > 0) ? (
                <div className="space-y-2">
                    <Label htmlFor="batch_id">
                        Lote {type === 'entry' ? '(Opcional)' : ''}
                    </Label>
                    <Select
                        value={
                            form.data.batch_id === '' && type === 'entry'
                                ? '__new__'
                                : form.data.batch_id
                        }
                        onValueChange={(value) => {
                            const isNew = value === '__new__' || value === '';
                            const targetBatchId = isNew ? '' : value;

                            if (type === 'entry') {
                                const selectedBatch = !isNew
                                    ? filteredBatches.find(
                                          (b) => String(b.id) === targetBatchId,
                                      )
                                    : undefined;

                                form.setData((prev: any) => ({
                                    ...prev,
                                    batch_id: targetBatchId,
                                    cost_price:
                                        selectedBatch?.unit_price !==
                                            undefined &&
                                        selectedBatch?.unit_price !== null &&
                                        selectedBatch?.unit_price !== ''
                                            ? String(selectedBatch.unit_price)
                                            : prev.batch_id !== targetBatchId
                                              ? ''
                                              : prev.cost_price,
                                }));
                            } else {
                                form.setData('batch_id', targetBatchId);
                            }
                        }}
                        disabled={!form.data.raw_material_id}
                    >
                        <SelectTrigger id="batch_id">
                            <SelectValue
                                placeholder={
                                    form.data.raw_material_id
                                        ? type === 'exit' &&
                                          filteredBatches.length === 0
                                            ? 'No hay lotes disponibles'
                                            : 'Selecciona lote'
                                        : 'Selecciona MP primero'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {type === 'entry' && (
                                <SelectItem value="__new__">
                                    + Registrar como nuevo lote
                                </SelectItem>
                            )}
                            {filteredBatches.map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.lot_number ?? `Auto #${b.id}`} (Disp:{' '}
                                    {formatNumber(b.remaining_quantity, {
                                        maxDecimals: 2,
                                    })}
                                    )
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.batch_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.batch_id}
                        </p>
                    )}
                </div>
            ) : (
                <div className="space-y-2">
                    <Label htmlFor="batch_id">Lote</Label>
                    <Input
                        id="batch_id"
                        value="Se generará uno nuevo"
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
                    onChange={(e: ChangeEvent<HTMLInputElement>) =>
                        form.setData('quantity', e.target.value)
                    }
                    placeholder="0.0000"
                />
                {form.errors.quantity && (
                    <p className="text-sm text-destructive">
                        {form.errors.quantity}
                    </p>
                )}
            </div>

            {children}

            <div className="space-y-2">
                <Label htmlFor="movement_date">Fecha del Movimiento</Label>
                <Input
                    id="movement_date"
                    type="date"
                    value={form.data.movement_date}
                    onChange={(e: ChangeEvent<HTMLInputElement>) =>
                        form.setData('movement_date', e.target.value)
                    }
                />
                {form.errors.movement_date && (
                    <p className="text-sm text-destructive">
                        {form.errors.movement_date}
                    </p>
                )}
            </div>

            <div className="space-y-2 md:col-span-2">
                <Label htmlFor="notes">Notas / Observaciones</Label>
                <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(e: ChangeEvent<HTMLTextAreaElement>) =>
                        form.setData('notes', e.target.value)
                    }
                    placeholder={
                        type === 'entry'
                            ? 'Detalles adicionales de la entrada...'
                            : 'Motivo de la salida (ej: merma, consumo producción...)'
                    }
                    className="min-h-[80px]"
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
