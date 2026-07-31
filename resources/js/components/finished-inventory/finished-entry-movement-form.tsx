import { useForm } from '@inertiajs/react';
import type { SubmitEvent } from 'react';

import { Button } from '@/components/ui/button';
import { store as storeFinishedMovement } from '@/routes/finished-inventory-movements';
import { entryReasons } from '@/types/finished-inventory';
import type {
    FinishedBatchOption,
    FinishedMovementReason,
    FinishedWarehouseOption,
} from '@/types/finished-inventory';
import { FinishedMovementFormFields } from './finished-movement-form-fields';
import type { FinishedMovementFormData } from './finished-movement-form-fields';

type Props = {
    batches: FinishedBatchOption[];
    warehouses: FinishedWarehouseOption[];
    defaultWarehouseId?: number | null;
    onSuccess?: () => void;
};

export function FinishedEntryMovementForm({
    batches,
    warehouses,
    defaultWarehouseId,
    onSuccess,
}: Props) {
    const form = useForm<FinishedMovementFormData>({
        finished_product_batch_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        type: 'entry' as const,
        reason: 'adjustment' as FinishedMovementReason,
        quantity: '',
        movement_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            finished_product_batch_id: Number(data.finished_product_batch_id),
            warehouse_id: Number(data.warehouse_id),
            quantity: data.quantity,
        }));

        form.post(storeFinishedMovement.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                onSuccess?.();
            },
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-6">
            <FinishedMovementFormFields
                form={form}
                batches={batches}
                warehouses={warehouses}
                reasons={entryReasons}
            />

            <div className="flex justify-end border-t pt-4">
                <Button
                    type="submit"
                    disabled={form.processing}
                    className="w-full bg-emerald-600 text-white hover:bg-emerald-700 sm:w-auto"
                >
                    {form.processing ? 'Registrando...' : 'Registrar entrada'}
                </Button>
            </div>
        </form>
    );
}
