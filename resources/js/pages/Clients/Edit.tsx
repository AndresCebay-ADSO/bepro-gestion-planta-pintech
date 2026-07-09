import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Building2 } from 'lucide-react';

import { update as clientUpdate } from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/clients/ClientForm';
import { Button } from '@/components/ui/button';
import { index as clientsIndex } from '@/routes/clients';

type ClientData = {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
    shipping_address: string | null;
};

type Props = {
    client: ClientData;
};

export default function ClientsEdit({ client }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        business_name: client.business_name,
        nit: client.nit ?? '',
        contact_name: client.contact_name ?? '',
        phone: client.phone ?? '',
        shipping_address: client.shipping_address ?? '',
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(clientUpdate(client.id).url);
    };

    return (
        <>
            <Head title="Editar Cliente" />

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
                            Editar Cliente
                        </h1>
                    </div>
                </div>

                <ClientForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Actualizar Cliente"
                />
            </div>
        </>
    );
}
