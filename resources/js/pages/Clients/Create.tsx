import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';

import { store as clientStore } from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/clients/ClientForm';
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
            <Head title="Nuevo Cliente" />

            <div className="mx-auto max-w-2xl space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={clientsIndex().url}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver
                        </Link>
                    </Button>
                    <div className="flex items-center gap-2">
                        <Building2 className="h-5 w-5 text-muted-foreground" />
                        <h1 className="text-2xl font-semibold">
                            Nuevo Cliente
                        </h1>
                    </div>
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
