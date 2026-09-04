import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';
import { getLocalDateString } from '@/lib/date-time-helpers';
import { MovementFormBase } from './movement-form-base';

type Option = {
    id: number;
    name?: string;
    code?: string;
    lot_number?: string;
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
    defaultWarehouseId?: number;
    onSuccess?: () => void;
};

export function ExitMovementForm({
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
        type: 'exit';
        quantity: string;
        movement_date: string;
        notes: string;
    }>({
        raw_material_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        batch_id: '',
        type: 'exit',
        quantity: '',
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
                type="exit"
            />

            <div className="flex justify-end border-t pt-4">
                <Button
                    type="submit"
                    disabled={form.processing}
                    variant="destructive"
                    className="w-full bg-amber-600 text-white hover:bg-amber-700 sm:w-auto"
                >
                    {form.processing
                        ? 'Registrando Salida...'
                        : 'Registrar Salida'}
                </Button>
            </div>
        </form>
    );
}
