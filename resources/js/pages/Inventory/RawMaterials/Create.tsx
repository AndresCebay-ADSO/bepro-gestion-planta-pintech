import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { RawMaterialForm } from '@/components/raw-materials/raw-material-form';
import { Button } from '@/components/ui/button';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type CategoryOption = {
    id: number;
    name: string;
    code: string;
};

type Props = {
    categories: CategoryOption[];
    units: UnitOption[];
};

type RawMaterialFormData = {
    code: string;
    category_id: string;
    unit_of_measure_id: string;
    current_price: string;
    previous_price: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    is_active: boolean;
};

export default function RawMaterialsCreate({ categories, units }: Props) {
    const form = useForm<RawMaterialFormData>({
        code: '',
        category_id: '',
        unit_of_measure_id: '',
        current_price: '',
        previous_price: '',
        minimum_stock: '0',
        alert_days_before_expiry: '30',
        is_active: true,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            unit_of_measure_id: Number(data.unit_of_measure_id),
            previous_price:
                data.previous_price === '' ? null : data.previous_price,
        }));

        form.post(route('raw-materials.store'));
    };

    return (
        <>
            <Head title="Nueva Materia Prima" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={route('raw-materials.index')}
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
                />

                <div className="flex justify-end gap-2 pt-2 pr-2">
                    <Button variant="outline" asChild>
                        <Link href={route('raw-materials.index')}>
                            Cancelar
                        </Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
