import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { FormPage } from '@/components/form-page';
import {
    FormulaForm,
    formulaPayload,
} from '@/components/formulas/formula-form';
import type { FormulaFormData } from '@/components/formulas/formula-form';
import { PageHeader } from '@/components/page-header';
import { formatForInput } from '@/lib/formatters';
import { formulaCrumb } from '@/lib/formula-name';
import {
    edit as formulasEdit,
    index as formulasIndex,
    show as formulasShow,
    update as formulasUpdate,
} from '@/routes/formulas';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; name: string };
type UnitOption = { id: number; name: string; symbol: string };

type Props = {
    formula: {
        id: number;
        version: number;
        name: string | null;
        notes: string | null;
        product: {
            id: number;
            name: string;
        };
        details: Array<{
            raw_material_id: number;
            quantity: string;
            unit_of_measure_id: number;
        }>;
    };
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
};

export default function FormulasEdit({
    formula,
    products,
    rawMaterials,
    units,
}: Props) {
    const { data, setData, processing, errors, clearErrors } =
        useForm<FormulaFormData>({
            product_id: String(formula.product.id),
            name: formula.name ?? '',
            notes: formula.notes ?? '',
            details: formula.details.map((detail) => ({
                raw_material_id: String(detail.raw_material_id),
                quantity: formatForInput(detail.quantity),
                unit_of_measure_id: String(detail.unit_of_measure_id),
            })),
        });

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        router.put(
            formulasUpdate({ formula: formula.id }).url,
            formulaPayload(data),
        );
    };

    return (
        <>
            <Head
                title={`Editar fórmula v${formula.version} — ${formula.product.name}`}
            />

            <FormPage width="wide">
                <PageHeader
                    title={`Editar fórmula v${formula.version}`}
                    description="Corrige materiales, cantidades o notas sin cambiar el producto asociado."
                />

                <FormulaForm
                    data={data}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    products={products}
                    rawMaterials={rawMaterials}
                    units={units}
                    heading="Información General"
                    description="Ajusta los ingredientes por paso. Si esta fórmula ya fue usada en órdenes, la edición se bloquea para proteger el histórico."
                    submitLabel="Guardar cambios"
                    cancelHref={formulasShow({ formula: formula.id }).url}
                    onSubmit={handleSubmit}
                    setData={setData}
                    clearErrors={clearErrors}
                    lockProduct
                />
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver (DESIGN.md §10).
FormulasEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Fórmulas', href: formulasIndex() },
        {
            title: formulaCrumb(props.formula),
            href: formulasShow({ formula: props.formula.id }),
        },
        { title: 'Editar', href: formulasEdit({ formula: props.formula.id }) },
    ],
});
