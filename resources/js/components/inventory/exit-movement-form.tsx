import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
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
    defaultWarehouseId?: number;
    onSuccess?: () => void;
};

export function ExitMovementForm({
    rawMaterials,
    batches,
    warehouses,
    productionOrders,
    defaultWarehouseId,
    onSuccess,
}: Props) {
    const form = useForm<{
        raw_material_id: string;
        warehouse_id: string;
        batch_id: string;
        production_order_id: string;
        type: 'exit';
        quantity: string;
        movement_date: string;
        notes: string;
    }>({
        raw_material_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        batch_id: '',
        production_order_id: '',
        type: 'exit',
        quantity: '',
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
            // cost_price is deliberately omitted for exits. The backend ignores it.
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
                type="exit"
            />

            <div className="flex justify-end border-t pt-4">
                <Button type="submit" disabled={form.processing} variant="destructive" className="w-full sm:w-auto bg-amber-600 hover:bg-amber-700 text-white">
                    {form.processing ? 'Registrando Salida...' : 'Registrar Salida'}
                </Button>
            </div>
        </form>
    );
}
