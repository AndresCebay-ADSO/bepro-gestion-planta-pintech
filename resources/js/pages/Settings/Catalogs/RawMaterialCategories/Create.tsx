import { Head, Link, useForm } from '@inertiajs/react';
import { RawMaterialCategoryFields } from '@/components/catalogs/raw-material-category-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    create as categoriesCreate,
    index as categoriesIndex,
    store as categoriesStore,
} from '@/routes/catalogs/raw-material-categories';
import type { RawMaterialCategoryFormData } from '@/types';

type Props = {
    typeOptions: { value: string; label: string }[];
};

export default function RawMaterialCategoriesCreate({ typeOptions }: Props) {
    const form = useForm<RawMaterialCategoryFormData>({
        code: '',
        name: '',
        description: '',
        type: 'chemical',
        is_active: true,
    });

    return (
        <>
            <Head title="Nueva categoría de materia prima" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Nueva categoría de materia prima"
                    description="Elige bien el tipo de insumo: no se podrá cambiar cuando la categoría tenga materias primas."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(categoriesStore().url);
                    }}
                    className="grid gap-6"
                >
                    <RawMaterialCategoryFields
                        form={form}
                        typeOptions={typeOptions}
                    />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando...' : 'Guardar'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={categoriesIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

RawMaterialCategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Categorías de materia prima', href: categoriesIndex() },
        { title: 'Nueva', href: categoriesCreate() },
    ],
};
