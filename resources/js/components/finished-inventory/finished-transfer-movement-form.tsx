import { useForm, usePage } from '@inertiajs/react';
import type { SubmitEvent } from 'react';

import { FormActions } from '@/components/form-actions';
import { SheetBody, SheetFooter } from '@/components/ui/sheet';
import { idPayload } from '@/lib/numeric-input';
import { store as storeFinishedMovement } from '@/routes/finished-inventory-movements';
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
    onCancel: () => void;
};

export function FinishedTransferMovementForm({
    batches,
    warehouses,
    defaultWarehouseId,
    onSuccess,
    onCancel,
}: Props) {
    const { plantToday } = usePage().props;
    const form = useForm<FinishedMovementFormData>({
        finished_product_batch_id: '',
        warehouse_id: defaultWarehouseId ? String(defaultWarehouseId) : '',
        destination_warehouse_id: '',
        type: 'exit' as const,
        reason: 'transfer' as FinishedMovementReason,
        quantity: '',
        movement_date: plantToday,
        notes: '',
    });

    const submit = (event: SubmitEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            finished_product_batch_id: idPayload(
                data.finished_product_batch_id,
            ),
            warehouse_id: idPayload(data.warehouse_id),
            destination_warehouse_id: idPayload(data.destination_warehouse_id),
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
        <form
            noValidate
            onSubmit={submit}
            className="flex min-h-0 flex-1 flex-col"
        >
            <SheetBody>
                <FinishedMovementFormFields
                    form={form}
                    batches={batches}
                    warehouses={warehouses}
                    showDestinationWarehouse
                />
            </SheetBody>

            <SheetFooter>
                <FormActions
                    submitLabel="Registrar traslado"
                    processing={form.processing}
                    onCancel={onCancel}
                />
            </SheetFooter>
        </form>
    );
}
