import type { useForm } from '@inertiajs/react';
import type { FormEvent, ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '../ui/textarea';
import { formatNumber } from '@/lib/formatters';

type Option = {
    id: number;
    name?: string;
    code?: string;
    lot_number?: string;
    order_number?: string;
    city?: string;
    type?: string;
    raw_material_id?: number | string;
    remaining_quantity?: string | number;
    status?: string;
};

type Props = {
    form: ReturnType<typeof useForm<any>>;
    rawMaterials: Option[];
    batches: Option[];
    warehouses: Option[];
    productionOrders: Option[];
    onSubmit: () => void;
    submitLabel?: string;
};

export function MovementForm({
    form,
    rawMaterials,
    batches,
    warehouses,
    productionOrders,
    onSubmit,
    submitLabel = 'Guardar',
}: Props) {
    // Filter batches based on selected raw material
    const filteredBatches = batches.filter(
        (b) => Number(b.raw_material_id) === Number(form.data.raw_material_id),
    );

    return (
        <form
            onSubmit={(e: FormEvent<HTMLFormElement>) => {
                e.preventDefault();
                onSubmit();
            }}
            className="space-y-6"
        >
            <div className="grid grid-cols-1 gap-6 md:grid-cols-2">
                {/* Bodega */}
                <div className="space-y-2">
                    <Label htmlFor="warehouse_id">Bodega</Label>
                    <Select
                        value={form.data.warehouse_id}
                        onValueChange={(value) =>
                            form.setData('warehouse_id', value)
                        }
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

                {/* Materia Prima */}
                <div className="space-y-2">
                    <Label htmlFor="raw_material_id">Materia Prima</Label>
                    <Select
                        value={form.data.raw_material_id}
                        onValueChange={(value) => {
                            form.setData({
                                ...form.data,
                                raw_material_id: value,
                                batch_id: '', // Reset batch when material changes
                            });
                        }}
                    >
                        <SelectTrigger id="raw_material_id">
                            <SelectValue placeholder="Selecciona materia prima" />
                        </SelectTrigger>
                        <SelectContent>
                            {rawMaterials.map((rm) => (
                                <SelectItem key={rm.id} value={String(rm.id)}>
                                    {rm.code}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.raw_material_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.raw_material_id}
                        </p>
                    )}
                </div>

                {/* Tipo de Movimiento */}
                <div className="space-y-2">
                    <Label htmlFor="type">Tipo de Movimiento</Label>
                    <Select
                        value={form.data.type}
                        onValueChange={(value) => form.setData('type', value)}
                    >
                        <SelectTrigger id="type">
                            <SelectValue placeholder="Selecciona tipo" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="entry">Entrada</SelectItem>
                            <SelectItem value="exit">Salida</SelectItem>
                        </SelectContent>
                    </Select>
                    {form.errors.type && (
                        <p className="text-sm text-destructive">
                            {form.errors.type}
                        </p>
                    )}
                </div>

                {/* Lote (Opcional en entrada, Obligatorio en salida según backend) */}
                <div className="space-y-2">
                    <Label htmlFor="batch_id">Lote (Opcional en Entrada)</Label>
                    <Select
                        value={form.data.batch_id}
                        onValueChange={(value) =>
                            form.setData('batch_id', value)
                        }
                        disabled={!form.data.raw_material_id}
                    >
                        <SelectTrigger id="batch_id">
                            <SelectValue
                                placeholder={
                                    form.data.raw_material_id
                                        ? 'Selecciona lote'
                                        : 'Selecciona MP primero'
                                }
                            />
                        </SelectTrigger>
                        <SelectContent>
                            {filteredBatches.map((b) => (
                                <SelectItem key={b.id} value={String(b.id)}>
                                    {b.lot_number} (Disp: {formatNumber(b.remaining_quantity, { maxDecimals: 2 })})
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

                {/* Cantidad */}
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

                {/* Precio de Costo */}
                <div className="space-y-2">
                    <Label htmlFor="cost_price">Precio de Costo (u)</Label>
                    <Input
                        id="cost_price"
                        type="number"
                        step="0.0001"
                        value={form.data.cost_price}
                        onChange={(e: ChangeEvent<HTMLInputElement>) =>
                            form.setData('cost_price', e.target.value)
                        }
                        placeholder="0.00"
                    />
                    {form.errors.cost_price && (
                        <p className="text-sm text-destructive">
                            {form.errors.cost_price}
                        </p>
                    )}
                </div>

                {/* Fecha */}
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

                {/* Orden de Producción (Opcional) */}
                <div className="space-y-2">
                    <Label htmlFor="production_order_id">
                        Orden de Producción (Opcional)
                    </Label>
                    <Select
                        value={form.data.production_order_id}
                        onValueChange={(value) =>
                            form.setData('production_order_id', value)
                        }
                    >
                        <SelectTrigger id="production_order_id">
                            <SelectValue placeholder="Vincular a orden" />
                        </SelectTrigger>
                        <SelectContent>
                            {productionOrders.map((po) => (
                                <SelectItem key={po.id} value={String(po.id)}>
                                    {po.order_number} ({po.status})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.production_order_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.production_order_id}
                        </p>
                    )}
                </div>
            </div>

            {/* Notas */}
            <div className="space-y-2">
                <Label htmlFor="notes">Notas / Observaciones</Label>
                <Textarea
                    id="notes"
                    value={form.data.notes}
                    onChange={(e: ChangeEvent<HTMLTextAreaElement>) =>
                        form.setData('notes', e.target.value)
                    }
                    placeholder="Detalles adicionales del movimiento..."
                    className="min-h-[100px]"
                />
                {form.errors.notes && (
                    <p className="text-sm text-destructive">
                        {form.errors.notes}
                    </p>
                )}
            </div>

            <div className="flex justify-end pt-4">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Procesando...' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
