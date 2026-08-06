import { Head } from '@inertiajs/react';

import type { RawPaintDevFormData } from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import PaintDevelopmentRequestForm from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import {
    index as requestsIndex,
    update as requestsUpdate,
} from '@/routes/paint-development-requests';

type Props = {
    request: RawPaintDevFormData & { id: number };
};

export default function PaintDevelopmentRequestEdit({ request }: Props) {
    return (
        <>
            <Head title={`Editar solicitud ${request.id}`} />
            <PaintDevelopmentRequestForm
                initialData={request}
                submitUrl={requestsUpdate(request.id).url}
                method="put"
                title="Editar solicitud de desarrollo"
                backUrl={requestsIndex().url}
                saveLabel="Guardar cambios"
                submitLabel="Guardar y enviar"
            />
        </>
    );
}
