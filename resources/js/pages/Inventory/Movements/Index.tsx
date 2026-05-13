import { Head, useForm, router, usePage } from '@inertiajs/react';
import { Search, ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';
import { useState, useEffect, useCallback, useRef } from 'react';
import type { FormEvent } from 'react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { EntryMovementForm } from '@/components/inventory/entry-movement-form';
import { ExitMovementForm } from '@/components/inventory/exit-movement-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';

import { index as inventoryMovementsIndex } from '@/routes/inventory-movements';
import type { PaginationLink } from '@/types/ui';

type Option = {
    id: number;
    name?: string;
    code?: string;
    lot_number?: string;
    city?: string;
    type?: string;
    raw_material_id?: number | string;
    remaining_quantity?: string | number;
    status?: string;
};

type Props = {
    movements: {
        data: Array<{
            id: number;
            type: 'entry' | 'exit';
            quantity: string;
            movement_date: string;
            raw_material?: { code: string } | null;
            warehouse?: { name: string; city: string } | null;
        }>;
        links: PaginationLink[];
    };
    rawMaterials?: Option[];
    batches?: Option[];
    warehouses?: Option[];
    can: { create: boolean };
    currentWarehouseId?: number | null;
    filters: {
        search?: string;
    };
};

export default function InventoryMovementsIndex({
    movements,
    rawMaterials,
    batches,
    warehouses,
    can,
    currentWarehouseId,
    filters,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const [drawerState, setDrawerState] = useState<{
        isOpen: boolean;
        mode: 'entry' | 'exit';
    }>({ isOpen: false, mode: 'entry' });
    const [isLoadingFormData, setIsLoadingFormData] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const flash = usePage<{ flash?: { success?: string; error?: string } }>()
        .props.flash;

    const openDrawer = useCallback(
        (mode: 'entry' | 'exit') => {
            setDrawerState({ isOpen: true, mode });
            setFetchError(null);

            // Lazy load form dependencies if missing
            if (!rawMaterials || !batches || !warehouses) {
                setIsLoadingFormData(true);
                router.reload({
                    only: ['rawMaterials', 'batches', 'warehouses'],
                    onSuccess: () => setIsLoadingFormData(false),
                    onError: () => {
                        setIsLoadingFormData(false);
                        setFetchError(
                            'Error de red al cargar los datos. Por favor, intente nuevamente.',
                        );
                    },
                });
            }
        },
        [rawMaterials, batches, warehouses],
    );

    const hasOpenedFromUrl = useRef(false);

    useEffect(() => {
        if (hasOpenedFromUrl.current) {
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const openParam = params.get('open');

        if (can.create && (openParam === 'entry' || openParam === 'exit')) {
            hasOpenedFromUrl.current = true;
            // Use setTimeout to avoid synchronous setState inside effect (cascading renders)
            setTimeout(() => {
                openDrawer(openParam);
            }, 0);
        }
    }, [can.create, openDrawer]);

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        get(inventoryMovementsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const onSuccessForm = () => {
        setDrawerState((prev) => ({ ...prev, isOpen: false }));
        router.reload({ only: ['movements'] });
    };

    return (
        <>
            <Head title="Movimientos de inventario" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Movimientos de Inventario
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Historial de entradas y salidas de materias primas.
                        </p>
                    </div>
                    {can.create && (
                        <div className="flex items-center gap-2">
                            <Button
                                onClick={() => openDrawer('entry')}
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <ArrowDownToLine className="mr-2 h-4 w-4" />
                                Entrada
                            </Button>
                            <Button
                                onClick={() => openDrawer('exit')}
                                className="bg-amber-600 text-white hover:bg-amber-700"
                            >
                                <ArrowUpFromLine className="mr-2 h-4 w-4" />
                                Salida
                            </Button>
                        </div>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form
                        onSubmit={handleSearch}
                        className="relative w-full max-w-sm"
                    >
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por código de insumo..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="pl-10"
                        />
                    </form>
                </div>

                {flash?.success && (
                    <div className="rounded-md border border-emerald-500/25 bg-emerald-500/10 px-4 py-2 text-sm text-emerald-700 dark:text-emerald-300">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <div className="rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Tipo
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Bodega
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Materia Prima
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Cantidad
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.data.map((movement) => (
                                <tr
                                    key={movement.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4">
                                        <FormattedDate
                                            value={movement.movement_date}
                                        />
                                    </td>
                                    <td className="p-4">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                movement.type === 'entry'
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                            }`}
                                        >
                                            {movement.type === 'entry'
                                                ? 'ENTRADA'
                                                : 'SALIDA'}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        <div className="font-medium text-foreground">
                                            {movement.warehouse?.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {movement.warehouse?.city}
                                        </div>
                                    </td>
                                    <td className="p-4 font-mono">
                                        {movement.raw_material?.code ?? '-'}
                                    </td>
                                    <td className="p-4 text-right font-medium">
                                        <FormattedNumber
                                            value={movement.quantity}
                                            maxDecimals={2}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {movements.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="p-8 text-center text-muted-foreground"
                                    >
                                        No hay movimientos registrados para los
                                        filtros seleccionados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={movements.links} />
                </div>
            </div>

            <Sheet
                open={drawerState.isOpen}
                onOpenChange={(open) =>
                    setDrawerState((prev) => ({ ...prev, isOpen: open }))
                }
                modal={false}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-2xl"
                >
                    <SheetHeader className="mb-6 flex-shrink-0">
                        <SheetTitle className="flex items-center gap-2">
                            {drawerState.mode === 'entry' ? (
                                <>
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                                        <ArrowDownToLine className="h-4 w-4" />
                                    </span>
                                    Registrar Entrada
                                </>
                            ) : (
                                <>
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                        <ArrowUpFromLine className="h-4 w-4" />
                                    </span>
                                    Registrar Salida
                                </>
                            )}
                        </SheetTitle>
                        <SheetDescription>
                            {drawerState.mode === 'entry'
                                ? 'Ingresa nueva mercancía al inventario. Al guardar, el inventario aumentará.'
                                : 'Retira mercancía del inventario. El sistema verificará la disponibilidad en el lote.'}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="-mr-2 flex-1 overflow-y-auto pr-2">
                        {isLoadingFormData ? (
                            <div className="space-y-6">
                                <div className="grid grid-cols-2 gap-6">
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/4" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/4" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/4" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/4" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                    <div className="space-y-2">
                                        <Skeleton className="h-4 w-1/4" />
                                        <Skeleton className="h-10 w-full" />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Skeleton className="h-4 w-1/4" />
                                    <Skeleton className="h-24 w-full" />
                                </div>
                            </div>
                        ) : fetchError ? (
                            <div className="mt-6 rounded-md border border-destructive/50 bg-destructive/10 p-4 text-center">
                                <p className="text-sm font-medium text-destructive">
                                    {fetchError}
                                </p>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() => openDrawer(drawerState.mode)}
                                >
                                    Reintentar
                                </Button>
                            </div>
                        ) : rawMaterials && batches && warehouses ? (
                            drawerState.mode === 'entry' ? (
                                <EntryMovementForm
                                    rawMaterials={rawMaterials}
                                    batches={batches}
                                    warehouses={warehouses}
                                    defaultWarehouseId={
                                        currentWarehouseId ?? undefined
                                    }
                                    onSuccess={onSuccessForm}
                                />
                            ) : (
                                <ExitMovementForm
                                    rawMaterials={rawMaterials}
                                    batches={batches}
                                    warehouses={warehouses}
                                    defaultWarehouseId={
                                        currentWarehouseId ?? undefined
                                    }
                                    onSuccess={onSuccessForm}
                                />
                            )
                        ) : (
                            <div className="py-8 text-center text-muted-foreground">
                                No se pudieron cargar los datos del formulario.
                            </div>
                        )}
                    </div>
                </SheetContent>
            </Sheet>
        </>
    );
}
