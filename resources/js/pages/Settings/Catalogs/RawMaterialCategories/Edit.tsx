import { Head, useForm } from '@inertiajs/react';
import { RawMaterialCategoryFields } from '@/components/catalogs/raw-material-category-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    edit as categoriesEdit,
    index as categoriesIndex,
    update as categoriesUpdate,
} from '@/routes/catalogs/raw-material-categories';
import type {
    RawMaterialCategoryFormData,
    RawMaterialCategoryRow,
} from '@/types';

type Props = {
    category: RawMaterialCategoryRow & { description: string | null };
    typeOptions: { value: string; label: string }[];
};

export default function RawMaterialCategoriesEdit({
    category,
    typeOptions,
}: Props) {
    const form = useForm<RawMaterialCategoryFormData>({
        code: category.code,
        name: category.name,
        description: category.description ?? '',
        type: category.type,
        is_active: category.is_active,
    });

    return (
        <>
            <Head title={`Editar ${category.name}`} />

            <div className="space-y-6">
                <Heading
                    title={`Editar «${category.name}»`}
                    description={`Materias primas en esta categoría: ${category.raw_materials_count}.`}
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(categoriesUpdate(category.id).url);
                    }}
                    className="grid gap-6"
                >
                    <RawMaterialCategoryFields
                        form={form}
                        typeOptions={typeOptions}
                        rawMaterialsCount={category.raw_materials_count}
                    />

                    <FormActions
                        submitLabel="Guardar cambios"

                        processing={form.processing}

                        cancelHref={categoriesIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

RawMaterialCategoriesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Categorías de materia prima', href: categoriesIndex() },
        {
            title: props.category.name,
            href: categoriesEdit(props.category.id),
        },
    ],
});
