import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { RawMaterialForm } from '@/components/raw-materials/raw-material-form';
import type {
    CategoryOption,
    RawMaterialFormData,
} from '@/components/raw-materials/raw-material-form';
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
    index as rawMaterialsIndex,
    show as rawMaterialsShow,
    update as rawMaterialsUpdate,
} from '@/routes/raw-materials';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type RawMaterial = {
    id: number;
    code: string;
    category_id: number | null;
    unit_of_measure_id: number;
    minimum_stock: string;
    alert_days_before_expiry: number;
    price_variation_threshold: string | null;
    tracks_inventory: boolean;
    /** Solo llega con permiso de costos. */
    current_price?: string | null;
    is_active: boolean;
};

type Props = {
    rawMaterial: RawMaterial;
    categories: CategoryOption[];
    units: UnitOption[];
    can: { updateCosts: boolean };
    /** OP abiertas que la usan: activar el control de inventario las dejaría sin saldo para completarse. */
    openProductionOrdersCount: number;
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
    can,
    openProductionOrdersCount,
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
        tracks_inventory: rawMaterial.tracks_inventory,
        current_price: trimZeroes(rawMaterial.current_price),
        is_active: rawMaterial.is_active,
    });

    const [confirming, setConfirming] = useState(false);
    const startsTracking =
        !rawMaterial.tracks_inventory && form.data.tracks_inventory;
    // El servidor también la exige si una OP empezó a usarla mientras se editaba.
    const confirmationError = (
        form.errors as Partial<Record<'confirm_tracking_change', string>>
    ).confirm_tracking_change;
    const needsConfirmation =
        startsTracking &&
        (openProductionOrdersCount > 0 || Boolean(confirmationError));

    const save = (confirmed: boolean) => {
        form.transform(({ current_price, ...data }) => ({
            ...data,
            confirm_tracking_change: confirmed,
            // El precio solo viaja si se puede escribir: sin control de inventario y con permiso de costos.
            ...(!data.tracks_inventory && can.updateCosts
                ? { current_price: current_price === '' ? null : current_price }
                : {}),
            unit_of_measure_id: Number(data.unit_of_measure_id),
            price_variation_threshold:
                data.price_variation_threshold === ''
                    ? null
                    : data.price_variation_threshold,
        }));

        form.put(rawMaterialsUpdate(rawMaterial.id).url, {
            onSuccess: () => setConfirming(false),
            // Si una OP empezó a usarla mientras se editaba, el servidor pide confirmación: se abre el diálogo con su
            // mensaje en vez de dejar el error sin mostrar.
            onError: (errors) =>
                setConfirming(Boolean(errors.confirm_tracking_change)),
        });
    };

    const submit = () => {
        if (needsConfirmation) {
            setConfirming(true);

            return;
        }

        save(false);
    };

    return (
        <>
            <Head title={`Editar ${rawMaterial.code}`} />

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
                        <Link
                            href={rawMaterialsShow(rawMaterial.id).url}
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
                    canEditPrice={can.updateCosts}
                />

                <div className="flex justify-end gap-2 pt-2 pr-2">
                    <Button variant="outline" asChild>
                        <Link href={rawMaterialsIndex().url}>Cancelar</Link>
                    </Button>
                </div>
            </div>

            <AlertDialog open={confirming} onOpenChange={setConfirming}>
                <AlertDialogContent>
                    <AlertDialogHeader>
                        <AlertDialogTitle>
                            ¿Activar el control de inventario de{' '}
                            {rawMaterial.code}?
                        </AlertDialogTitle>
                        <AlertDialogDescription asChild>
                            <div className="space-y-2">
                                <p>
                                    {confirmationError ??
                                        `${openProductionOrdersCount === 1 ? 'Una orden de producción abierta la usa' : `${openProductionOrdersCount} órdenes de producción abiertas la usan`}.`}
                                </p>
                                <p>
                                    Desde ahora necesitará saldo: esas órdenes
                                    no se podrán completar hasta registrar sus
                                    compras, y su precio saldrá de ellas.
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
                            Sí, activar el control
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
