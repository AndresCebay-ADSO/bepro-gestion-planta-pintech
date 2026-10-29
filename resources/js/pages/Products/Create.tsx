import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { ProductForm } from '@/components/products/product-form';
import type { ProductFormData } from '@/components/products/product-form';
import { withDecimalPayloads } from '@/lib/numeric-input';
import { PRODUCT_DECIMAL_FIELDS } from '@/lib/product-form';
import {
    create as productsCreate,
    index as productsIndex,
} from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean };
};

export default function ProductsCreate({ categories, units, can }: Props) {
    const { data, setData, post, transform, processing, errors } =
        useForm<ProductFormData>({
            name: '',
            description: '',
            category_id: '',
            unit_of_measure_id: '',
            cif_percentage: '0',
            price_threshold: '0',
            quality_viscosity_lower: '',
            quality_viscosity_upper: '',
            quality_fineness_lower: '',
            quality_fineness_upper: '',
            quality_solids_lower: '',
            quality_solids_upper: '',
            is_active: true,
        });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        transform((form) => withDecimalPayloads(form, PRODUCT_DECIMAL_FIELDS));
        post(productsIndex().url);
    };

    return (
        <>
            <Head title="Nuevo producto" />
            <FormPage width="narrow">
                <PageHeader
                    title="Nuevo producto"
                    description="Registra un nuevo producto de pintura en el catálogo."
                />

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    categories={categories}
                    units={units}
                    onSubmit={handleSubmit}
                    submitLabel="Crear producto"
                    cancelHref={productsIndex().url}
                    showPrices={can.managePrices}
                    costHelp="Se calculará automáticamente cuando se registre la fórmula de producción."
                />
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ProductsCreate.layout = {
    breadcrumbs: [
        { title: 'Productos', href: productsIndex() },
        { title: 'Nuevo producto', href: productsCreate() },
    ],
};
