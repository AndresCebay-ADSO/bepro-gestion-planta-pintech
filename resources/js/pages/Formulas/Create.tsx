import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormPage } from '@/components/form-page';
import {
    createEmptyFormulaDetail,
    FormulaForm,
    formulaPayload,
} from '@/components/formulas/formula-form';
import type { FormulaFormData } from '@/components/formulas/formula-form';
import { PageHeader } from '@/components/page-header';
import { breadcrumbHref } from '@/lib/navigation';
import {
    create as formulasCreate,
    index as formulasIndex,
    store as formulasStore,
} from '@/routes/formulas';
import {
    index as productsIndex,
    show as productsShow,
} from '@/routes/products';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; name: string };
type UnitOption = { id: number; name: string; symbol: string };

type FormulaCreateFormData = FormulaFormData & {
    return_to: string;
};

type Props = {
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
    selectedProductId?: string | null;
    returnTo?: string | null;
};

export default function FormulasCreate({
    products,
    rawMaterials,
    units,
    selectedProductId,
    returnTo,
}: Props) {
    const { data, setData, processing, errors, clearErrors } =
        useForm<FormulaCreateFormData>({
            product_id: selectedProductId ?? '',
            name: '',
            notes: '',
            details: [createEmptyFormulaDetail()],
            return_to: returnTo ?? '',
        });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.post(formulasStore().url, formulaPayload(data));
    };

    return (
        <>
            <Head title="Nueva fórmula" />
            <FormPage width="wide">
                <PageHeader
                    title="Nueva fórmula"
                    description="Define los ingredientes en el orden de proceso (paso 1, 2, 3…). La misma materia prima puede repetirse en pasos distintos. Las cantidades son por galón. Esta fórmula alimenta consumos y órdenes de producción."
                />

                <FormulaForm
                    data={data}
                    errors={errors}
                    processing={processing}
                    products={products}
                    rawMaterials={rawMaterials}
                    units={units}
                    heading="Información General"
                    description="Una fila por paso; el orden importa en planta. Cantidades por 1 galón (ej. 1.5 kg de resina por galón)."
                    submitLabel="Crear Fórmula"
                    cancelHref={returnTo ?? formulasIndex().url}
                    onSubmit={handleSubmit}
                    setData={setData}
                    clearErrors={clearErrors}
                    lockProduct={Boolean(selectedProductId)}
                />
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver (DESIGN.md §10). Desde un producto (`product_id`) vuelven a ese producto, que
// llega en `returnTo`; desde el listado de fórmulas, al listado con sus filtros.
FormulasCreate.layout = (props: Props) => {
    const product = props.selectedProductId
        ? props.products.find(
              (option) => String(option.id) === String(props.selectedProductId),
          )
        : undefined;

    if (product) {
        return {
            breadcrumbs: [
                { title: 'Productos', href: productsIndex() },
                {
                    title: product.name,
                    href: breadcrumbHref(
                        props.returnTo,
                        productsShow(product.id).url,
                    ),
                },
                { title: 'Nueva fórmula', href: formulasCreate() },
            ],
        };
    }

    return {
        breadcrumbs: [
            {
                title: 'Fórmulas',
                href: breadcrumbHref(props.returnTo, formulasIndex().url),
            },
            { title: 'Nueva fórmula', href: formulasCreate() },
        ],
    };
};
