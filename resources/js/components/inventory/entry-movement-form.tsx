import { useForm } from '@inertiajs/react';
import type { FormEvent, ChangeEvent } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { getLocalDateString } from '@/lib/date-time-helpers';
import type { InventoryOption } from '@/types';
import { MovementFormBase } from './movement-form-base';

type Props = {
    rawMaterials: InventoryOption[];
    batches: InventoryOption[];
    warehouses: InventoryOption[];
    defaultWarehouseId?: number;
    onSuccess?: () => void;
};

export function EntryMovementForm({
    rawMaterials,
    batches,
    warehouses,
    defaultWarehouseId,
    onSuccess,
}: Props) {
    const form = useForm<{
        raw_material_id: string;
        warehouse_id: string;
        batch_id: string;
        type: 'entry';
        quantity: string;
        cost_price: string;
        lot_number: string;
        supplier: string;
        expiry_date: string;
        movement_date: string;
        notes: string;
    }>({
        raw_material_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        batch_id: '',
        type: 'entry',
        quantity: '',
        cost_price: '',
        lot_number: '',
        supplier: '',
        expiry_date: '',
        movement_date: getLocalDateString(),
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            raw_material_id: Number(data.raw_material_id),
            warehouse_id: Number(data.warehouse_id),
            batch_id: data.batch_id === '' ? null : Number(data.batch_id),
            quantity: Number(data.quantity),
            cost_price:
                data.cost_price === '' ? null : Number(data.cost_price),
            lot_number: data.batch_id === '' ? data.lot_number : null,
            supplier:
                data.batch_id === '' && data.supplier !== ''
                    ? data.supplier
                    : null,
            expiry_date:
                data.batch_id === '' && data.expiry_date !== ''
                    ? data.expiry_date
                    : null,
        }));

        form.post(route('inventory-movements.store'), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
            onError: () => {
                // Let Inertia display inline errors automatically via form.errors
            },
        });
    };

    const selectedBatch = form.data.batch_id
        ? batches.find((b) => String(b.id) === form.data.batch_id)
        : undefined;
    const batchHasPrice =
        selectedBatch?.unit_price !== undefined &&
        selectedBatch?.unit_price !== null &&
        selectedBatch.unit_price !== '';
    const isCostReadOnly = Boolean(form.data.batch_id !== '' && batchHasPrice);

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <MovementFormBase
                form={form}
                rawMaterials={rawMaterials}
                batches={batches}
                warehouses={warehouses}
                type="entry"
            >
                <div className="space-y-2">
                    <Label htmlFor="cost_price">Precio de Costo (u)</Label>
                    <Input
                        id="cost_price"
                        type="number"
                        step="0.0001"
                        value={form.data.cost_price}
                        onChange={(e: ChangeEvent<HTMLInputElement>) => {
                            if (!isCostReadOnly) {
                                form.setData('cost_price', e.target.value);
                            }
                        }}
                        readOnly={isCostReadOnly}
                        className={
                            isCostReadOnly
                                ? 'bg-muted/50 cursor-not-allowed'
                                : ''
                        }
                        placeholder="0.00"
                    />
                    {isCostReadOnly && (
                        <p className="text-xs text-muted-foreground">
                            Costo fijado por el lote seleccionado.
                        </p>
                    )}
                    {form.errors.cost_price && (
                        <p className="text-sm text-destructive">
                            {form.errors.cost_price}
                        </p>
                    )}
                </div>

                {form.data.batch_id === '' && (
                    <>
                        <div className="space-y-2">
                            <Label htmlFor="lot_number">Número de lote</Label>
                            <Input
                                id="lot_number"
                                value={form.data.lot_number}
                                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                    form.setData('lot_number', e.target.value)
                                }
                                placeholder="Ej. LOT-2026-001"
                            />
                            {form.errors.lot_number && (
                                <p className="text-sm text-destructive">
                                    {form.errors.lot_number}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="supplier">Proveedor</Label>
                            <Input
                                id="supplier"
                                value={form.data.supplier}
                                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                    form.setData('supplier', e.target.value)
                                }
                                placeholder="Proveedor"
                            />
                            {form.errors.supplier && (
                                <p className="text-sm text-destructive">
                                    {form.errors.supplier}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="expiry_date">
                                Fecha de vencimiento
                            </Label>
                            <Input
                                id="expiry_date"
                                type="date"
                                value={form.data.expiry_date}
                                onChange={(e: ChangeEvent<HTMLInputElement>) =>
                                    form.setData('expiry_date', e.target.value)
                                }
                            />
                            {form.errors.expiry_date && (
                                <p className="text-sm text-destructive">
                                    {form.errors.expiry_date}
                                </p>
                            )}
                        </div>
                    </>
                )}
            </MovementFormBase>

            <div className="flex justify-end border-t pt-4">
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="w-full sm:w-auto"
                >
                    {form.processing
                        ? 'Registrando Entrada...'
                        : 'Registrar Entrada'}
                </Button>
            </div>
        </form>
    );
}
