import { Head, useForm } from '@inertiajs/react';
import { FormActions } from '@/components/form-actions';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { WarehouseFields } from '@/components/warehouses/warehouse-form';
import type { WarehouseFormData } from '@/components/warehouses/warehouse-form';
import {
    edit as warehousesEdit,
    index as warehousesIndex,
    show as warehousesShow,
    update as warehousesUpdate,
} from '@/routes/warehouses';

type Props = {
    warehouse: {
        id: number;
        name: string;
        city: string;
        address: string | null;
        type: 'factory' | 'storage';
        is_active: boolean;
    };
};

export default function WarehousesEdit({ warehouse }: Props) {
    const form = useForm<WarehouseFormData>({
        name: warehouse.name,
        city: warehouse.city,
        address: warehouse.address ?? '',
        type: warehouse.type,
        is_active: warehouse.is_active,
    });

    const submit = () => {
        form.put(warehousesUpdate(warehouse.id).url);
    };

    return (
        <>
            <Head title={`Editar bodega: ${warehouse.name}`} />

            <FormPage width="narrow">
                <PageHeader
                    title={`Editar bodega: ${warehouse.name}`}
                    description={`Actualiza la información de ${warehouse.name}.`}
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        submit();
                    }}
                    className="space-y-6"
                >
                    <div className="rounded-lg border border-border bg-card p-6">
                        <WarehouseFields form={form} lockType />
                    </div>

                    <FormActions
                        submitLabel="Guardar cambios"
                        processing={form.processing}
                        cancelHref={warehousesIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
}

WarehousesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Bodegas', href: warehousesIndex() },
        {
            title: props.warehouse.name,
            href: warehousesShow(props.warehouse.id),
        },
        { title: 'Editar', href: warehousesEdit(props.warehouse.id) },
    ],
});
