import { Head, Link, useForm } from '@inertiajs/react';
import { ProductCategoryFields } from '@/components/catalogs/product-category-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
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
                    variant="small"
                    title="Nueva categoría de producto"
                    description="Quedará disponible al registrar productos."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(categoriesStore().url);
                    }}
                    className="grid gap-6"
                >
                    <ProductCategoryFields form={form} />

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

ProductCategoriesCreate.layout = {
    breadcrumbs: [
        { title: 'Categorías de producto', href: categoriesIndex() },
        { title: 'Nueva', href: categoriesCreate() },
    ],
};
