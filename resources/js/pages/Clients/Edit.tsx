import { Head, useForm } from '@inertiajs/react';

import { update as clientUpdate } from '@/actions/App/Http/Controllers/ClientController';
import ClientForm from '@/components/clients/ClientForm';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { edit as clientsEdit, index as clientsIndex } from '@/routes/clients';

type ClientData = {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
    shipping_address: string | null;
    is_active: boolean;
};

type Props = {
    client: ClientData;
    can: { deactivate: boolean };
};

export default function ClientsEdit({ client, can }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        business_name: client.business_name,
        nit: client.nit ?? '',
        contact_name: client.contact_name ?? '',
        phone: client.phone ?? '',
        shipping_address: client.shipping_address ?? '',
        is_active: client.is_active,
    });

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        put(clientUpdate(client.id).url);
    };

    return (
        <>
            <Head title={`Editar cliente: ${client.business_name}`} />

            <FormPage width="narrow">
                <PageHeader title={`Editar cliente: ${client.business_name}`} />

                <ClientForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    onSubmit={handleSubmit}
                    submitLabel="Guardar cambios"
                >
                    {can.deactivate && (
                        <div className="space-y-1">
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <Label htmlFor="is_active">
                                    Cliente activo
                                </Label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Un cliente inactivo no aparece al crear
                                cotizaciones ni pedidos; los que ya tiene siguen
                                su curso.
                            </p>
                        </div>
                    )}
                </ClientForm>
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ClientsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Clientes', href: clientsIndex() },
        {
            title: props.client.business_name,
            href: clientsEdit(props.client.id),
        },
    ],
});
