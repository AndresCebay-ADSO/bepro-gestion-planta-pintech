import { Head, Link, useForm } from '@inertiajs/react';
import { ShrinkWrapTypeFields } from '@/components/catalogs/shrink-wrap-type-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
                    variant="small"
                    title={`Editar «${type.name}»`}
                    description="Los termoencogidos ya registrados conservan la receta con que se registraron."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(typesUpdate(type.id).url);
                    }}
                    className="grid gap-6"
                >
                    <ShrinkWrapTypeFields
                        form={form}
                        rawMaterialOptions={rawMaterialOptions}
                    />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={typesIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
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
