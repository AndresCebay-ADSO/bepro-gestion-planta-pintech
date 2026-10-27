import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

import { store as clientStore } from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/clients/ClientForm';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { index as clientsIndex } from '@/routes/clients';

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

            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="space-y-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={clientsIndex().url}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver
                        </Link>
                    </Button>
                    <PageHeader title="Nuevo cliente" />
                </div>

                <ClientForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Guardar Cliente"
                />
            </div>
        </>
    );
}
