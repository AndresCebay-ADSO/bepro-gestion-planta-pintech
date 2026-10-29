import { Head, useForm } from '@inertiajs/react';
import {
    ShrinkWrapTypeFields,
    shrinkWrapTypePayload,
} from '@/components/catalogs/shrink-wrap-type-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    edit as typesEdit,
    index as typesIndex,
    update as typesUpdate,
} from '@/routes/catalogs/shrink-wrap-types';
import type {
    ShrinkWrapRawMaterialOption,
    ShrinkWrapTypeFormData,
    ShrinkWrapTypeRow,
} from '@/types';

type Props = {
    type: ShrinkWrapTypeRow;
    rawMaterialOptions: ShrinkWrapRawMaterialOption[];
};

export default function ShrinkWrapTypesEdit({
    type,
    rawMaterialOptions,
}: Props) {
    const form = useForm<ShrinkWrapTypeFormData>({
        name: type.name,
        is_active: type.is_active,
        items: type.items.map((item) => ({
            raw_material_id: String(item.raw_material_id),
            quantity: item.quantity,
        })),
    });

    return (
        <>
            <Head title={`Editar ${type.name}`} />

            <div className="space-y-6">
                <Heading
                    title={`Editar «${type.name}»`}
                    description="Los termoencogidos ya registrados conservan la receta con que se registraron."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform(shrinkWrapTypePayload);
                        form.put(typesUpdate(type.id).url);
                    }}
                    className="grid gap-6"
                >
                    <ShrinkWrapTypeFields
                        form={form}
                        rawMaterialOptions={rawMaterialOptions}
                    />

                    <FormActions
                        submitLabel="Guardar cambios"

                        processing={form.processing}

                        cancelHref={typesIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

ShrinkWrapTypesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Tipos de termoencogido', href: typesIndex() },
        { title: props.type.name, href: typesEdit(props.type.id) },
    ],
});
