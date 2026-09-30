import { Head, Link, useForm } from '@inertiajs/react';
import { RawMaterialForm } from '@/components/raw-materials/raw-material-form';
import type {
    CategoryOption,
    RawMaterialFormData,
} from '@/components/raw-materials/raw-material-form';
import { Button } from '@/components/ui/button';
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
        form.transform(({ current_price, ...data }) => ({
            ...data,
            category_id: Number(data.category_id),
            unit_of_measure_id: Number(data.unit_of_measure_id),
            price_variation_threshold:
                data.price_variation_threshold === ''
                    ? null
                    : data.price_variation_threshold,
            // El precio solo viaja si se puede escribir: sin control de inventario y con permiso de costos.
            ...(!data.tracks_inventory && can.updateCosts
                ? { current_price: current_price === '' ? null : current_price }
                : {}),
        }));

        form.post(rawMaterialsStore().url);
    };

    return (
        <>
            <Head title="Nueva Materia Prima" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={rawMaterialsIndex().url}
                            className="hover:text-foreground"
                        >
                            Materias Primas
                        </Link>
                        <span>/</span>
                        <span>Crear</span>
                    </div>
                    <h1 className="text-2xl font-semibold">
                        Nueva Materia Prima
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Registra una nueva materia prima base para inventario.
                    </p>
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
