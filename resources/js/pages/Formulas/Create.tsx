import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { DetailPageHeader } from '@/components/detail-page-header';
import {
    createEmptyFormulaDetail,
    FormulaForm,
} from '@/components/formulas/formula-form';
import type { FormulaFormData } from '@/components/formulas/formula-form';
import {
    index as formulasIndex,
    store as formulasStore,
} from '@/routes/formulas';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; code: string; name: string };
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
    const { data, setData, processing, errors } =
        useForm<FormulaCreateFormData>({
            product_id: selectedProductId ?? '',
            notes: '',
            details: [createEmptyFormulaDetail()],
            return_to: returnTo ?? '',
        });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.post(formulasStore().url, {
            ...data,
            details: data.details.map((detail) => ({
                ...detail,
                quantity: detail.quantity.replace(',', '.'),
            })),
        });
    };

    const breadcrumbs = returnTo
        ? [
              {
                  title: 'Productos',
                  href: returnTo,
              },
              { title: 'Nueva fórmula', href: '#' },
          ]
        : [
              { title: 'Fórmulas', href: formulasIndex().url },
              { title: 'Nueva', href: '#' },
          ];

    return (
        <>
            <Head title="Nueva Fórmula" />
            <div className="space-y-6 p-6">
                <DetailPageHeader
                    breadcrumbs={breadcrumbs}
                    title="Nueva Fórmula"
                    subtitle="Define los ingredientes en el orden de proceso (paso 1, 2, 3…). La misma materia prima puede repetirse en pasos distintos. Las cantidades son por galón. Esta fórmula alimenta consumos y órdenes de producción."
                    returnTo={returnTo}
                    defaultReturnHref={formulasIndex().url}
                    defaultReturnLabel="Fórmulas"
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
                    onSubmit={handleSubmit}
                    setData={setData}
                    lockProduct={Boolean(selectedProductId)}
                />
            </div>
        </>
    );
}
