import { Head, useForm } from '@inertiajs/react';
import { SupplierFields } from '@/components/catalogs/supplier-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    edit as suppliersEdit,
    index as suppliersIndex,
    update as suppliersUpdate,
} from '@/routes/catalogs/suppliers';
import type { SupplierFormData, SupplierRow } from '@/types';

type Props = {
    supplier: SupplierRow & { email: string | null; notes: string | null };
};

export default function SuppliersEdit({ supplier }: Props) {
    const form = useForm<SupplierFormData>({
        name: supplier.name,
        nit: supplier.nit ?? '',
        contact_name: supplier.contact_name ?? '',
        phone: supplier.phone ?? '',
        email: supplier.email ?? '',
        notes: supplier.notes ?? '',
        is_active: supplier.is_active,
    });

    return (
        <>
            <Head title={`Editar ${supplier.name}`} />

            <div className="space-y-6">
                <Heading
                    title={`Editar «${supplier.name}»`}
                    description="Los lotes ya registrados conservan su proveedor."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(suppliersUpdate(supplier.id).url);
                    }}
                    className="grid gap-6"
                >
                    <SupplierFields form={form} />

                    <FormActions
                        submitLabel="Guardar cambios"
                        processing={form.processing}
                        cancelHref={suppliersIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

SuppliersEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Proveedores', href: suppliersIndex() },
        { title: props.supplier.name, href: suppliersEdit(props.supplier.id) },
    ],
});
