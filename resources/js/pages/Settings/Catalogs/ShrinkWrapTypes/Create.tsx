import { Head, useForm } from '@inertiajs/react';
import {
    ShrinkWrapTypeFields,
    shrinkWrapTypePayload,
} from '@/components/catalogs/shrink-wrap-type-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    create as typesCreate,
    index as typesIndex,
    store as typesStore,
} from '@/routes/catalogs/shrink-wrap-types';
import type {
    ShrinkWrapRawMaterialOption,
    ShrinkWrapTypeFormData,
} from '@/types';

type Props = {
    rawMaterialOptions: ShrinkWrapRawMaterialOption[];
};

export default function ShrinkWrapTypesCreate({ rawMaterialOptions }: Props) {
    const form = useForm<ShrinkWrapTypeFormData>({
        name: '',
        is_active: true,
        items: [{ raw_material_id: '', quantity: '1' }],
    });

    return (
        <>
            <Head title="Nuevo tipo de termoencogido" />

            <div className="space-y-6">
                <Heading
                    title="Nuevo tipo de termoencogido"
                    description="Ej.: «Galón» gasta 1 bolsa y 1 bandeja cada vez que se termoencoge."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform(shrinkWrapTypePayload);
                        form.post(typesStore().url);
                    }}
                    className="grid gap-6"
                >
                    <ShrinkWrapTypeFields
                        form={form}
                        rawMaterialOptions={rawMaterialOptions}
                    />

                    <FormActions
                        submitLabel="Guardar"

                        processing={form.processing}

                        cancelHref={typesIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

ShrinkWrapTypesCreate.layout = {
    breadcrumbs: [
        { title: 'Tipos de termoencogido', href: typesIndex() },
        { title: 'Nuevo tipo de termoencogido', href: typesCreate() },
    ],
};
