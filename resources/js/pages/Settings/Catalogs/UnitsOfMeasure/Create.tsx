import { Head, useForm } from '@inertiajs/react';
import {
    UNIT_FACTOR_FIELDS,
    UnitOfMeasureFields,
} from '@/components/catalogs/unit-of-measure-form';
import { FormActions } from '@/components/form-actions';
import Heading from '@/components/heading';
import { withDecimalPayloads } from '@/lib/numeric-input';
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
                    title="Nueva unidad de medida"
                    description="Quedará disponible para materias primas, productos, presentaciones y fórmulas."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.transform((data) =>
                            withDecimalPayloads(data, UNIT_FACTOR_FIELDS),
                        );
                        form.post(unitsStore().url);
                    }}
                    className="grid gap-6"
                >
                    <UnitOfMeasureFields form={form} />

                    <FormActions
                        submitLabel="Guardar"

                        processing={form.processing}

                        cancelHref={unitsIndex().url}
                    />
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
