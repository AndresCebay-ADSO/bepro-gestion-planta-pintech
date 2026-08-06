import { Head } from '@inertiajs/react';

import PaintDevelopmentRequestForm from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import {
    index as requestsIndex,
    store as requestsStore,
} from '@/routes/paint-development-requests';

export default function PaintDevelopmentRequestCreate() {
    return (
        <>
            <Head title="Nueva solicitud de desarrollo" />
            <PaintDevelopmentRequestForm
                initialData={{}}
                submitUrl={requestsStore().url}
                method="post"
                title="Solicitud de desarrollo de una pintura nueva"
                backUrl={requestsIndex().url}
                saveLabel="Guardar borrador"
                submitLabel="Guardar y enviar"
            />
        </>
    );
}
