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

type RawMaterial = {
    id: number;
    code: string;
    category_id: number | null;
    unit_of_measure_id: number;
    minimum_stock: string;
    alert_days_before_expiry: number;
    price_variation_threshold: string | null;
    is_active: boolean;
};

type Props = {
    rawMaterial: RawMaterial;
    categories: CategoryOption[];
    units: UnitOption[];
};

type RawMaterialFormData = {
    code: string;
    category_id: string;
    unit_of_measure_id: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    price_variation_threshold: string;
    is_active: boolean;
};

const trimZeroes = (val: string | null | undefined): string => {
    if (!val) {
        return '';
    }

    return val.includes('.') ? val.replace(/0+$/, '').replace(/\.$/, '') : val;
};

export default function RawMaterialsEdit({
    rawMaterial,
    categories,
    units,
}: Props) {
    const form = useForm<RawMaterialFormData>({
        code: rawMaterial.code,
        category_id: rawMaterial.category_id
            ? String(rawMaterial.category_id)
            : '',
        unit_of_measure_id: String(rawMaterial.unit_of_measure_id),
        minimum_stock: trimZeroes(rawMaterial.minimum_stock),
        alert_days_before_expiry: String(rawMaterial.alert_days_before_expiry),
        price_variation_threshold: trimZeroes(
            rawMaterial.price_variation_threshold,
        ),
        is_active: rawMaterial.is_active,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            unit_of_measure_id: Number(data.unit_of_measure_id),
            price_variation_threshold:
                data.price_variation_threshold === ''
                    ? null
                    : data.price_variation_threshold,
        }));

        form.put(route('raw-materials.update', rawMaterial.id));
    };

    return (
        <>
            <Head title={`Editar ${rawMaterial.code}`} />

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
                        <Link
                            href={route('raw-materials.show', rawMaterial.id)}
                            className="font-mono hover:text-foreground"
                        >
                            {rawMaterial.code}
                        </Link>
                        <span>/</span>
                        <span>Editar</span>
                    </div>
                    <h1 className="text-2xl font-semibold">
                        Editar Materia Prima:{' '}
                        <span className="font-mono">{rawMaterial.code}</span>
                    </h1>
                </div>

                <RawMaterialForm
                    form={form}
                    categories={categories}
                    units={units}
                    onSubmit={submit}
                    submitLabel="Guardar cambios"
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
