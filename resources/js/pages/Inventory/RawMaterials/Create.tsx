import { Head, Link, useForm } from '@inertiajs/react';
import { PageHeader } from '@/components/page-header';
import {
    RawMaterialForm,
    rawMaterialPayload,
} from '@/components/raw-materials/raw-material-form';
import type {
    CategoryOption,
    RawMaterialFormData,
} from '@/components/raw-materials/raw-material-form';
import { Button } from '@/components/ui/button';
import { idPayload } from '@/lib/numeric-input';
import {
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

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="space-y-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={rawMaterialsIndex().url}
                            className="hover:text-foreground"
                        >
                            Materias primas
                        </Link>
                        <span>/</span>
                        <span>Crear</span>
                    </div>
                    <PageHeader
                        title="Nueva materia prima"
                        description="Registra una nueva materia prima base para inventario."
                    />
                </div>

                <RawMaterialForm
                    form={form}
                    categories={categories}
                    units={units}
                    onSubmit={submit}
                    submitLabel="Crear Materia Prima"
                    canEditPrice={can.updateCosts}
                />

                <div className="flex justify-end gap-2 pt-2 pr-2">
                    <Button variant="outline" asChild>
                        <Link href={rawMaterialsIndex().url}>Cancelar</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
