import { Head, useForm } from '@inertiajs/react';
import { FormPage } from '@/components/form-page';
import { PageHeader } from '@/components/page-header';
import {
    RawMaterialForm,
    rawMaterialPayload,
} from '@/components/raw-materials/raw-material-form';
import type {
    CategoryOption,
    RawMaterialFormData,
} from '@/components/raw-materials/raw-material-form';
import { idPayload } from '@/lib/numeric-input';
import {
    create as rawMaterialsCreate,
    index as rawMaterialsIndex,
    store as rawMaterialsStore,
} from '@/routes/raw-materials';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type Props = {
    categories: CategoryOption[];
    units: UnitOption[];
    can: { updateCosts: boolean };
};

export default function RawMaterialsCreate({ categories, units, can }: Props) {
    const form = useForm<RawMaterialFormData>({
        code: '',
        category_id: '',
        unit_of_measure_id: '',
        minimum_stock: '0',
        alert_days_before_expiry: '30',
        price_variation_threshold: '',
        tracks_inventory: true,
        current_price: '',
        is_active: true,
    });

    const submit = () => {
        form.transform((data) => ({
            ...rawMaterialPayload(data, can.updateCosts),
            category_id: idPayload(data.category_id),
        }));

        form.post(rawMaterialsStore().url);
    };

    return (
        <>
            <Head title="Nueva materia prima" />

            <FormPage width="narrow">
                <PageHeader
                    title="Nueva materia prima"
                    description="Registra una nueva materia prima base para inventario."
                />

                <RawMaterialForm
                    form={form}
                    categories={categories}
                    units={units}
                    onSubmit={submit}
                    submitLabel="Crear materia prima"
                    cancelHref={rawMaterialsIndex().url}
                    canEditPrice={can.updateCosts}
                />
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
RawMaterialsCreate.layout = {
    breadcrumbs: [
        { title: 'Materias primas', href: rawMaterialsIndex() },
        { title: 'Nueva materia prima', href: rawMaterialsCreate() },
    ],
};
