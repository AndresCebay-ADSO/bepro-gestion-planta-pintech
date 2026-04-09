import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { RawMaterialForm } from '@/components/raw-materials/raw-material-form';
import { Button } from '@/components/ui/button';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type RawMaterial = {
    id: number;
    code: string;
    unit_of_measure_id: number;
    current_price: string;
    previous_price: string | null;
    minimum_stock: string;
    alert_days_before_expiry: number;
    is_active: boolean;
};

type Props = {
    rawMaterial: RawMaterial;
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

const trimZeroes = (val: string | null | undefined): string => {
    if (!val) return '';
    return val.includes('.') ? val.replace(/0+$/, '').replace(/\.$/, '') : val;
};

export default function RawMaterialsEdit({ rawMaterial, units }: Props) {
    const form = useForm<RawMaterialFormData>({
        code: rawMaterial.code,
        unit_of_measure_id: String(rawMaterial.unit_of_measure_id),
        current_price: trimZeroes(rawMaterial.current_price),
        previous_price: trimZeroes(rawMaterial.previous_price),
        minimum_stock: trimZeroes(rawMaterial.minimum_stock),
        alert_days_before_expiry: String(rawMaterial.alert_days_before_expiry),
        is_active: rawMaterial.is_active,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            unit_of_measure_id: Number(data.unit_of_measure_id),
            previous_price: data.previous_price === '' ? null : data.previous_price,
        }));

        form.put(route('raw-materials.update', rawMaterial.code));
    };

    return (
        <>
            <Head title={`Editar ${rawMaterial.code}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Editar Materia Prima</h1>
                    <p className="text-sm text-muted-foreground">
                        Actualiza {rawMaterial.code}
                    </p>
                </div>

                <div className="rounded-lg border bg-card p-6">
                    <RawMaterialForm
                        form={form}
                        units={units}
                        onSubmit={submit}
                        submitLabel="Guardar cambios"
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