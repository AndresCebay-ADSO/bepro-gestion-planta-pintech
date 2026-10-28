import { Head, useForm } from '@inertiajs/react';

import { store as clientStore } from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/clients/ClientForm';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import {
    create as clientsCreate,
    index as clientsIndex,
} from '@/routes/clients';

export default function ClientsCreate() {
    const { data, setData, post, processing, errors } = useForm({
        business_name: '',
        nit: '',
        contact_name: '',
        phone: '',
        shipping_address: '',
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(clientStore().url);
    };

    return (
        <>
            <Head title="Nuevo cliente" />

            <FormPage width="narrow">
                <PageHeader title="Nuevo cliente" />

                <ClientForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Guardar cliente"
                />
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ClientsCreate.layout = {
    breadcrumbs: [
        { title: 'Clientes', href: clientsIndex() },
        { title: 'Nuevo cliente', href: clientsCreate() },
    ],
};
