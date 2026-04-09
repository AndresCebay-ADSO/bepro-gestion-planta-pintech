import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { RawMaterialForm } from '@/components/raw-materials/raw-material-form';
import { Button } from '@/components/ui/button';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type Props = {
    units: UnitOption[];
};

type RawMaterialFormData = {
    code: string;
    unit_of_measure_id: string;
    current_price: string;
    previous_price: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    is_active: boolean;
};

export default function RawMaterialsCreate({ units }: Props) {
    const form = useForm<RawMaterialFormData>({
        code: '',
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
            previous_price: data.previous_price === '' ? null : data.previous_price,
        }));

        form.post(route('raw-materials.store'));
    };

    return (
        <>
            <Head title="Nueva Materia Prima" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Nueva Materia Prima</h1>
                    <p className="text-sm text-muted-foreground">
                        Registra una nueva materia prima.
                    </p>
                </div>

                <div className="rounded-lg border bg-card p-6">
                    <RawMaterialForm
                        form={form}
                        units={units}
                        onSubmit={submit}
                        submitLabel="Guardar"
                    />

                    <div className="pt-4">
                        <Button variant="outline" asChild>
                            <Link href={route('raw-materials.index')}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}