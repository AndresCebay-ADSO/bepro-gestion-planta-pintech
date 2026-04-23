import { useForm } from '@inertiajs/react';
import type { FormEvent, ChangeEvent } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { MovementFormBase } from './movement-form-base';

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
    rawMaterials: Option[];
    batches: Option[];
    warehouses: Option[];
    productionOrders: Option[];
    onSuccess?: () => void;
};

export function EntryMovementForm({
    rawMaterials,
    batches,
    warehouses,
    productionOrders,
    onSuccess,
}: Props) {
    const form = useForm({
        raw_material_id: '',
        warehouse_id: '',
        batch_id: '',
        production_order_id: '',
        type: 'entry',
        quantity: '',
        cost_price: '',
        movement_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            raw_material_id: Number(data.raw_material_id),
            warehouse_id: Number(data.warehouse_id),
            batch_id: data.batch_id === '' ? null : Number(data.batch_id),
            production_order_id:
                data.production_order_id === ''
                    ? null
                    : Number(data.production_order_id),
            quantity: Number(data.quantity),
            cost_price: Number(data.cost_price),
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

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <MovementFormBase
                form={form}
                rawMaterials={rawMaterials}
                batches={batches}
                warehouses={warehouses}
                productionOrders={productionOrders}
                type="entry"
            >
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
            </MovementFormBase>

            <div className="flex justify-end border-t pt-4">
                <Button type="submit" disabled={form.processing} className="w-full sm:w-auto">
                    {form.processing ? 'Registrando Entrada...' : 'Registrar Entrada'}
                </Button>
            </div>
        </form>
    );
}
