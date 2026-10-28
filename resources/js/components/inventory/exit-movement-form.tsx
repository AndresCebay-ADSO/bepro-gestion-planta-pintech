import { useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { FormActions } from '@/components/form-actions';
import { SheetBody, SheetFooter } from '@/components/ui/sheet';
import { idPayload, toDecimalPayload } from '@/lib/numeric-input';
import { store as inventoryMovementsStore } from '@/routes/inventory-movements';
import type { InventoryOption } from '@/types';
import { MovementFormBase } from './movement-form-base';

type Props = {
    rawMaterials: InventoryOption[];
    batches: InventoryOption[];
    warehouses: InventoryOption[];
    defaultWarehouseId?: number;
    onSuccess?: () => void;
    onCancel: () => void;
};

export function ExitMovementForm({
    rawMaterials,
    batches,
    warehouses,
    defaultWarehouseId,
    onSuccess,
    onCancel,
}: Props) {
    const { plantToday } = usePage().props;
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
        movement_date: plantToday,
        notes: '',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            raw_material_id: idPayload(data.raw_material_id),
            warehouse_id: idPayload(data.warehouse_id),
            batch_id: idPayload(data.batch_id),
            quantity: toDecimalPayload(data.quantity),
            // cost_price is deliberately omitted for exits. The backend ignores it.
        }));

        form.post(inventoryMovementsStore().url, {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    };

    return (
        <form
            noValidate
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col"
        >
            <SheetBody>
                <MovementFormBase
                    form={form}
                    rawMaterials={rawMaterials}
                    batches={batches}
                    warehouses={warehouses}
                    type="exit"
                />
            </SheetBody>

            <SheetFooter>
                <FormActions
                    submitLabel="Registrar salida"
                    processing={form.processing}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </form>
    );
}
