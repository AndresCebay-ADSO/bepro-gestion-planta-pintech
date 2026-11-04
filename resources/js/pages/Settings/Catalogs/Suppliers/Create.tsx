import { Head, useForm } from '@inertiajs/react';
import { SupplierFields } from '@/components/catalogs/supplier-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    create as suppliersCreate,
    index as suppliersIndex,
    store as suppliersStore,
} from '@/routes/catalogs/suppliers';
import type { SupplierFormData } from '@/types';

export default function SuppliersCreate() {
    const form = useForm<SupplierFormData>({
        name: '',
        nit: '',
        contact_name: '',
        phone: '',
        email: '',
        notes: '',
        is_active: true,
    });

    return (
        <>
            <Head title="Nuevo proveedor" />

            <div className="space-y-6">
                <Heading
                    title="Nuevo proveedor"
                    description="Quedará disponible al registrar entradas de materia prima."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(suppliersStore().url);
                    }}
                    className="grid gap-6"
                >
                    <SupplierFields form={form} />

                    <FormActions
                        submitLabel="Guardar"
                        processing={form.processing}
                        cancelHref={suppliersIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

SuppliersCreate.layout = {
    breadcrumbs: [
        { title: 'Proveedores', href: suppliersIndex() },
        { title: 'Nuevo proveedor', href: suppliersCreate() },
    ],
};
