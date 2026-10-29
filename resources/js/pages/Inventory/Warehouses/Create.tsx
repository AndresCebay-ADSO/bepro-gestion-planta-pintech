import { Head, useForm } from '@inertiajs/react';
import { FormActions } from '@/components/form-actions';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { WarehouseFields } from '@/components/warehouses/warehouse-form';
import type { WarehouseFormData } from '@/components/warehouses/warehouse-form';
import {
    create as warehousesCreate,
    index as warehousesIndex,
    store as warehousesStore,
} from '@/routes/warehouses';

export default function WarehousesCreate() {
    const form = useForm<WarehouseFormData>({
        name: '',
        city: '',
        address: '',
        type: 'storage',
        is_active: true,
    });

    const submit = () => {
        form.post(warehousesStore().url);
    };

    return (
        <>
            <Head title="Nueva bodega" />

            <FormPage width="narrow">
                <PageHeader
                    title="Nueva bodega"
                    description="Registra una nueva bodega para control de inventario."
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
                        <WarehouseFields form={form} />
                    </div>

                    <FormActions
                        submitLabel="Guardar bodega"
                        processing={form.processing}
                        cancelHref={warehousesIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
WarehousesCreate.layout = {
    breadcrumbs: [
        { title: 'Bodegas', href: warehousesIndex() },
        { title: 'Nueva bodega', href: warehousesCreate() },
    ],
};
