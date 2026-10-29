import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import { ProductForm } from '@/components/products/product-form';
import type { ProductFormData } from '@/components/products/product-form';
import { withDecimalPayloads } from '@/lib/numeric-input';
import { PRODUCT_DECIMAL_FIELDS } from '@/lib/product-form';
import {
    edit as productsEdit,
    index as productsIndex,
    show as productsShow,
} from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        category_id: number | null;
        unit_of_measure_id: number | null;
        current_cost?: string | null;
        cif_percentage?: string | null;
        current_price?: string | null;
        price_threshold?: string | null;
        quality_viscosity_lower: number | string | null;
        quality_viscosity_upper: number | string | null;
        quality_fineness_lower: number | string | null;
        quality_fineness_upper: number | string | null;
        quality_solids_lower: number | string | null;
        quality_solids_upper: number | string | null;
        is_active: boolean;
    };
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean; viewCosts: boolean; deactivate: boolean };
    hasPriceReferenceFormula?: boolean;
};

export default function ProductsEdit({
    product,
    categories,
    units,
    can,
    hasPriceReferenceFormula,
}: Props) {
    const toInput = (value: number | string | null | undefined): string =>
        value !== null && value !== undefined && value !== ''
            ? String(value)
            : '';

    const { data, setData, put, transform, processing, errors } =
        useForm<ProductFormData>({
            name: product.name,
            description: product.description ?? '',
            category_id: product.category_id ? String(product.category_id) : '',
            unit_of_measure_id: product.unit_of_measure_id
                ? String(product.unit_of_measure_id)
                : '',
            cif_percentage: product.cif_percentage ?? '0',
            price_threshold: product.price_threshold ?? '0',
            quality_viscosity_lower: toInput(product.quality_viscosity_lower),
            quality_viscosity_upper: toInput(product.quality_viscosity_upper),
            quality_fineness_lower: toInput(product.quality_fineness_lower),
            quality_fineness_upper: toInput(product.quality_fineness_upper),
            quality_solids_lower: toInput(product.quality_solids_lower),
            quality_solids_upper: toInput(product.quality_solids_upper),
            is_active: product.is_active,
        });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        // Sin costs.view no llegan CIF ni umbral: no se envían y el servidor conserva los guardados.
        transform((rawForm) => {
            const form = withDecimalPayloads(rawForm, PRODUCT_DECIMAL_FIELDS);

            if (can.viewCosts) {
                return form;
            }

            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const { cif_percentage, price_threshold, ...rest } = form;

            return rest;
        });
        put(productsShow({ product: product.id }).url);
    };

    return (
        <>
            <Head title={`Editar ${product.name}`} />
            <FormPage width="narrow">
                <PageHeader title={`Editar ${product.name}`} />

                <ProductForm
                    data={data}
                    setData={setData}
                    errors={errors}
                    processing={processing}
                    categories={categories}
                    units={units}
                    onSubmit={handleSubmit}
                    submitLabel="Guardar cambios"
                    cancelHref={productsShow({ product: product.id }).url}
                    showPrices={can.managePrices && can.viewCosts}
                    currentCost={toInput(product.current_cost)}
                    currentPrice={toInput(product.current_price)}
                    costHelp={
                        hasPriceReferenceFormula
                            ? 'Calculado automáticamente por la fórmula de referencia de precio.'
                            : 'Se calculará automáticamente cuando se registre la fórmula de producción.'
                    }
                    showActive={can.deactivate}
                />
            </FormPage>
        </>
    );
}

ProductsEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Productos', href: productsIndex() },
        {
            title: props.product.name,
            href: productsShow({ product: props.product.id }),
        },
        { title: 'Editar', href: productsEdit({ product: props.product.id }) },
    ],
});
