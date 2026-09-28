import { Head, Link, useForm } from '@inertiajs/react';
import { UnitOfMeasureFields } from '@/components/catalogs/unit-of-measure-form';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import {
    create as unitsCreate,
    index as unitsIndex,
    store as unitsStore,
} from '@/routes/catalogs/units-of-measure';
import type { UnitOfMeasureFormData } from '@/types';

export default function UnitsOfMeasureCreate() {
    const form = useForm<UnitOfMeasureFormData>({
        code: '',
        name: '',
        symbol: '',
        description: '',
        to_kg_conversion: '',
        to_liter_conversion: '',
        is_active: true,
        confirm_factor_change: false,
    });

    return (
        <>
            <Head title="Nueva unidad de medida" />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title="Nueva unidad de medida"
                    description="Quedará disponible para materias primas, productos, presentaciones y fórmulas."
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(unitsStore().url);
                    }}
                    className="grid gap-6"
                >
                    <UnitOfMeasureFields form={form} />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Guardando...' : 'Guardar'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={unitsIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

UnitsOfMeasureCreate.layout = {
    breadcrumbs: [
        { title: 'Unidades de medida', href: unitsIndex() },
        { title: 'Nueva', href: unitsCreate() },
    ],
};
