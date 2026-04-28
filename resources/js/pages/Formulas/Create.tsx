import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import {
    createEmptyFormulaDetail,
    FormulaForm,
} from '@/components/formulas/formula-form';
import type { FormulaFormData } from '@/components/formulas/formula-form';
import { Button } from '@/components/ui/button';
import {
    index as formulasIndex,
    store as formulasStore,
} from '@/routes/formulas';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; code: string; name: string };
type UnitOption = { id: number; name: string; symbol: string };

type Props = {
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
    selectedProductId?: string | null;
};

export default function FormulasCreate({
    products,
    rawMaterials,
    units,
    selectedProductId,
}: Props) {
    const { data, setData, processing, errors } = useForm<FormulaFormData>({
        product_id: selectedProductId ?? '',
        notes: '',
        details: [createEmptyFormulaDetail()],
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

    return (
        <>
            <Head title="Nueva Fórmula" />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={formulasIndex().url}
                            className="hover:text-foreground"
                        >
                            Fórmulas
                        </Link>
                        <span>/</span>
                        <span>Nueva</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Nueva Fórmula
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Define los ingredientes en el orden de proceso (paso 1,
                        2, 3…). La misma materia prima puede repetirse en pasos
                        distintos. Las cantidades son por galón. Esta fórmula
                        alimenta consumos y órdenes de producción.
                    </p>
                </div>

                <FormulaForm
                    data={data}
                    errors={errors as Record<string, string>}
                    processing={processing}
                    products={products}
                    rawMaterials={rawMaterials}
                    units={units}
                    heading="Información General"
                    description="Una fila por paso; el orden importa en planta. Cantidades por 1 galón (ej. 1.5 kg de resina por galón)."
                    submitLabel="Crear Fórmula"
                    onSubmit={handleSubmit}
                    setData={setData}
                />

                <div className="flex gap-3">
                    <Button type="button" variant="outline" asChild>
                        <Link href={formulasIndex().url}>Cancelar</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
