import { Head, useForm } from '@inertiajs/react';
import { ProductCategoryFields } from '@/components/catalogs/product-category-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    edit as categoriesEdit,
    index as categoriesIndex,
    update as categoriesUpdate,
} from '@/routes/catalogs/product-categories';
import type { ProductCategoryFormData, ProductCategoryRow } from '@/types';

type Props = {
    category: ProductCategoryRow;
};

export default function ProductCategoriesEdit({ category }: Props) {
    const form = useForm<ProductCategoryFormData>({
        name: category.name,
        description: category.description ?? '',
        is_active: category.is_active,
    });

    return (
        <>
            <Head title={`Editar ${category.name}`} />

            <div className="space-y-6">
                <Heading
                    title={`Editar «${category.name}»`}
                    description={`Productos en esta categoría: ${category.products_count}.`}
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(categoriesUpdate(category.id).url);
                    }}
                    className="grid gap-6"
                >
                    <ProductCategoryFields form={form} />

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

ProductCategoriesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Categorías de producto', href: categoriesIndex() },
        {
            title: props.category.name,
            href: categoriesEdit(props.category.id),
        },
    ],
});
