import { Head } from '@inertiajs/react';

import PaintDevelopmentRequestForm from '@/components/paint-development-requests/PaintDevelopmentRequestForm';
import {
    create as requestsCreate,
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
                title="Nueva solicitud de desarrollo"
                saveLabel="Guardar borrador"
                submitLabel="Guardar y enviar"
            />
        </>
    );
}

PaintDevelopmentRequestCreate.layout = {
    breadcrumbs: [
        { title: 'Desarrollo de pinturas', href: requestsIndex() },
        { title: 'Nueva solicitud', href: requestsCreate() },
    ],
};
