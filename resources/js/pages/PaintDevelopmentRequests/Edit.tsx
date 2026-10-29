import { Head } from '@inertiajs/react';

import type { RawPaintDevFormData } from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import PaintDevelopmentRequestForm from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import {
    edit as requestsEdit,
    index as requestsIndex,
    show as requestsShow,
    update as requestsUpdate,
} from '@/routes/paint-development-requests';

type Props = {
    request: RawPaintDevFormData & { id: number; request_number: number };
};

export default function PaintDevelopmentRequestEdit({ request }: Props) {
    return (
        <>
            <Head title={`Editar solicitud ${request.request_number}`} />
            <PaintDevelopmentRequestForm
                initialData={request}
                submitUrl={requestsUpdate(request.id).url}
                method="put"
                title="Editar solicitud de desarrollo"
                saveLabel="Guardar cambios"
                submitLabel="Guardar y enviar"
            />
        </>
    );
}

PaintDevelopmentRequestEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Desarrollo de pinturas', href: requestsIndex() },
        {
            title: `Solicitud ${props.request.request_number}`,
            href: requestsShow(props.request.id),
        },
        { title: 'Editar', href: requestsEdit(props.request.id) },
    ],
});
