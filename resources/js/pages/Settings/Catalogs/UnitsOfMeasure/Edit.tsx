import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    UnitEquivalence,
    UnitOfMeasureFields,
    describeConversionUsage,
    describeUnitUsage,
    sameFactor,
} from '@/components/catalogs/unit-of-measure-form';
import Heading from '@/components/heading';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { Button } from '@/components/ui/button';
import {
    edit as unitsEdit,
    index as unitsIndex,
    update as unitsUpdate,
} from '@/routes/catalogs/units-of-measure';
import type { UnitOfMeasureFormData, UnitOfMeasureRow } from '@/types';

type Props = {
    unit: UnitOfMeasureRow & { description: string | null };
};

export default function UnitsOfMeasureEdit({ unit }: Props) {
    const form = useForm<UnitOfMeasureFormData>({
        code: unit.code,
        name: unit.name,
        symbol: unit.symbol,
        description: unit.description ?? '',
        to_kg_conversion: unit.to_kg_conversion ?? '',
        to_liter_conversion: unit.to_liter_conversion ?? '',
        is_active: unit.is_active,
        confirm_factor_change: false,
    });
    const [confirming, setConfirming] = useState(false);

    const factorChanged =
        !sameFactor(unit.to_kg_conversion, form.data.to_kg_conversion) ||
        !sameFactor(unit.to_liter_conversion, form.data.to_liter_conversion);
    // El servidor también la exige si la unidad empezó a usarse después de abrir la página.
    const needsConfirmation =
        factorChanged &&
        (unit.affects_conversions ||
            Boolean(form.errors.confirm_factor_change));

    const save = (confirmed: boolean) => {
        form.transform((data) => ({
            ...data,
            confirm_factor_change: confirmed,
        }));
        form.put(unitsUpdate(unit.id).url, {
            onFinish: () => setConfirming(false),
        });
    };

    return (
        <>
            <Head title={`Editar ${unit.name}`} />

            <div className="space-y-6">
                <Heading
                    variant="small"
                    title={`Editar «${unit.name}»`}
                    description={`En uso: ${describeUnitUsage(unit.usage)}.`}
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();

                        if (needsConfirmation) {
                            setConfirming(true);

                            return;
                        }

                        save(false);
                    }}
                    className="grid gap-6"
                >
                    <UnitOfMeasureFields
                        form={form}
                        usageSummary={
                            unit.affects_conversions
                                ? describeConversionUsage(unit.usage)
                                : null
                        }
                    />

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Guardando...'
                                : 'Guardar cambios'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={unitsIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>

            <AlertDialog open={confirming} onOpenChange={setConfirming}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            ¿Cambiar la equivalencia de «{unit.name}»?
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-2">
                                <p>
                                    {unit.affects_conversions
                                        ? `La usan ${describeConversionUsage(unit.usage)}.`
                                        : 'Empezó a usarse en fórmulas o materias primas mientras la editabas.'}
                                </p>
                                <p>
                                    Antes:{' '}
                                    <UnitEquivalence
                                        symbol={unit.symbol}
                                        kg={unit.to_kg_conversion}
                                        liter={unit.to_liter_conversion}
                                        emptyLabel="no se convierte"
                                    />
                                    . Ahora:{' '}
                                    <UnitEquivalence
                                        emptyLabel="no se convierte"
                                        symbol={form.data.symbol || unit.symbol}
                                        kg={form.data.to_kg_conversion || null}
                                        liter={
                                            form.data.to_liter_conversion ||
                                            null
                                        }
                                    />
                                    .
                                </p>
                                <p>
                                    Las órdenes de producción que se creen desde
                                    ahora calcularán sus cantidades con la
                                    equivalencia nueva. Las ya creadas no
                                    cambian. El cambio queda en la auditoría.
                                </p>
                            </div>
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={form.processing}>
                            Cancelar
                        </AlertDialogCancel>
                        <AlertDialogAction
                            disabled={form.processing}
                            onClick={(event) => {
                                event.preventDefault();
                                save(true);
                            }}
                        >
                            Sí, cambiar la equivalencia
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}

UnitsOfMeasureEdit.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Unidades de medida', href: unitsIndex() },
        { title: props.unit.name, href: unitsEdit(props.unit.id) },
    ],
});
