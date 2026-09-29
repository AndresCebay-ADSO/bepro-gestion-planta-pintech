import { Head, Link, useForm } from '@inertiajs/react';
import { ProductCategoryFields } from '@/components/catalogs/product-category-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
                    variant="small"
                    title={`Editar «${category.name}»`}
                    description={`Productos en esta categoría: ${category.products_count}.`}
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put(categoriesUpdate(category.id).url);
                    }}
                    className="grid gap-6"
                >
                    <ProductCategoryFields form={form} />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'}
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

ProductCategoriesEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Categorías de producto', href: categoriesIndex() },
        {
            title: props.category.name,
            href: categoriesEdit(props.category.id),
        },
    ],
});
