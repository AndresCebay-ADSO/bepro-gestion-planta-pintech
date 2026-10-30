import { Head, useForm } from '@inertiajs/react';
import { ProductCategoryFields } from '@/components/catalogs/product-category-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import {
    create as categoriesCreate,
    index as categoriesIndex,
    store as categoriesStore,
} from '@/routes/catalogs/product-categories';
import type { ProductCategoryFormData } from '@/types';

export default function ProductCategoriesCreate() {
    const form = useForm<ProductCategoryFormData>({
        name: '',
        description: '',
        is_active: true,
    });

    return (
        <>
            <Head title="Nueva categoría de producto" />

            <div className="space-y-6">
                <Heading
                    title="Nueva categoría de producto"
                    description="Quedará disponible al registrar productos."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(categoriesStore().url);
                    }}
                    className="grid gap-6"
                >
                    <ProductCategoryFields form={form} />

                    <FormActions
                        submitLabel="Guardar"

                        processing={form.processing}

                        cancelHref={categoriesIndex().url}
                    />
                </form>
            </div>
        </>
    );
}

ProductCategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Categorías de producto', href: categoriesIndex() },
        { title: 'Nueva categoría de producto', href: categoriesCreate() },
    ],
};
