import { Head, Link, useForm } from '@inertiajs/react';
import { ShrinkWrapTypeFields } from '@/components/catalogs/shrink-wrap-type-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
                    variant="small"
                    title="Nuevo tipo de termoencogido"
                    description="Ej.: «Galón» gasta 1 bolsa y 1 bandeja cada vez que se termoencoge."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(typesStore().url);
                    }}
                    className="grid gap-6"
                >
                    <ShrinkWrapTypeFields
                        form={form}
                        rawMaterialOptions={rawMaterialOptions}
                    />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando...' : 'Guardar'}
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

ShrinkWrapTypesCreate.layout = {
    breadcrumbs: [
        { title: 'Tipos de termoencogido', href: typesIndex() },
        { title: 'Nuevo', href: typesCreate() },
    ],
};
