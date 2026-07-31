import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import {
    ArrowDownToLine,
    ArrowLeftRight,
    ArrowUpFromLine,
    Eye,
    Search,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { FormEvent } from 'react';

import { FinishedEntryMovementForm } from '@/components/finished-inventory/finished-entry-movement-form';
import { FinishedExitMovementForm } from '@/components/finished-inventory/finished-exit-movement-form';
import {
    FinishedMovementReasonBadge,
    FinishedMovementTypeBadge,
} from '@/components/finished-inventory/finished-movement-badges';
import { FinishedTransferMovementForm } from '@/components/finished-inventory/finished-transfer-movement-form';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
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
import {
    index as finishedMovementsIndex,
    show as showFinishedMovement,
} from '@/routes/finished-inventory-movements';
import type { FinishedMovementsPage } from '@/types/finished-inventory';

type DrawerMode = 'entry' | 'exit' | 'transfer';

const drawerCopy: Record<
    DrawerMode,
    {
        title: string;
        description: string;
        icon: typeof ArrowDownToLine;
        iconClassName: string;
    }
> = {
    entry: {
        title: 'Registrar entrada PT',
        description:
            'Aumenta stock disponible para un lote de producto terminado.',
        icon: ArrowDownToLine,
        iconClassName:
            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300',
    },
    exit: {
        title: 'Registrar salida PT',
        description:
            'Retira unidades de un lote y valida disponibilidad antes de guardar.',
        icon: ArrowUpFromLine,
        iconClassName:
            'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
    },
    transfer: {
        title: 'Registrar traslado PT',
        description:
            'Mueve stock de un lote entre bodegas manteniendo su trazabilidad.',
        icon: ArrowLeftRight,
        iconClassName:
            'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-300',
    },
};

export default function FinishedMovementsIndex({
    movements,
    batches,
    warehouses,
    can,
    currentWarehouseId,
    filters,
}: FinishedMovementsPage) {
    const searchForm = useForm({
        search: filters.search ?? '',
    });
    const [drawerState, setDrawerState] = useState<{
        isOpen: boolean;
        mode: DrawerMode;
    }>({ isOpen: false, mode: 'entry' });
    const [isLoadingFormData, setIsLoadingFormData] = useState(false);
    const [fetchError, setFetchError] = useState<string | null>(null);
    const hasOpenedFromUrl = useRef(false);
    const flash = usePage<{ flash?: { success?: string; error?: string } }>()
        .props.flash;

    const openDrawer = useCallback(
        (mode: DrawerMode) => {
            setDrawerState({ isOpen: true, mode });
            setFetchError(null);

            if (!batches || !warehouses) {
                setIsLoadingFormData(true);
                router.reload({
                    only: ['batches', 'warehouses', 'currentWarehouseId'],
                    onFinish: () => setIsLoadingFormData(false),
                    onError: () =>
                        setFetchError(
                            'No se pudieron cargar lotes y bodegas. Intenta nuevamente.',
                        ),
                });
            }
        },
        [batches, warehouses],
    );

    useEffect(() => {
        if (hasOpenedFromUrl.current || !can.create) {
            return;
        }

        const openParam = new URLSearchParams(window.location.search).get(
            'open',
        );

        if (
            openParam === 'entry' ||
            openParam === 'exit' ||
            openParam === 'transfer'
        ) {
            hasOpenedFromUrl.current = true;
            setTimeout(() => openDrawer(openParam), 0);
        }
    }, [can.create, openDrawer]);

    const handleSearch = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        searchForm.get(finishedMovementsIndex.url(), {
            preserveScroll: true,
            preserveState: true,
            replace: true,
        });
    };

    const closeDrawerAfterSuccess = () => {
        setDrawerState((state) => ({ ...state, isOpen: false }));
        router.reload({ only: ['movements', 'batches'] });
    };

    const drawer = drawerCopy[drawerState.mode];
    const DrawerIcon = drawer.icon;

    return (
        <>
            <Head title="Movimientos PT" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold text-foreground">
                            <ArrowLeftRight className="h-7 w-7 text-primary" />
                            Movimientos PT
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Trazabilidad de entradas, salidas y traslados de
                            producto terminado.
                        </p>
                    </div>

                    {can.create && (
                        <div className="flex flex-wrap items-center gap-2">
                            <Button
                                type="button"
                                onClick={() => openDrawer('entry')}
                                className="bg-emerald-600 text-white hover:bg-emerald-700"
                            >
                                <ArrowDownToLine className="mr-2 h-4 w-4" />
                                Entrada
                            </Button>
                            <Button
                                type="button"
                                onClick={() => openDrawer('exit')}
                                className="bg-amber-600 text-white hover:bg-amber-700"
                            >
                                <ArrowUpFromLine className="mr-2 h-4 w-4" />
                                Salida
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => openDrawer('transfer')}
                            >
                                <ArrowLeftRight className="mr-2 h-4 w-4" />
                                Traslado
                            </Button>
                        </div>
                    )}
                </div>

                <form
                    onSubmit={handleSearch}
                    className="relative w-full max-w-md"
                >
                    <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        placeholder="Buscar por producto o presentación..."
                        value={searchForm.data.search}
                        onChange={(event) =>
                            searchForm.setData('search', event.target.value)
                        }
                        className="pl-10"
                    />
                </form>

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

                <div className="overflow-x-auto rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Tipo
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Razón
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Producto
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Bodega
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Cantidad
                                </th>
                                <th className="p-3 text-left font-medium">
                                    Origen
                                </th>
                                <th className="p-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="p-10 text-center text-muted-foreground"
                                    >
                                        No hay movimientos PT registrados con
                                        los criterios seleccionados.
                                    </td>
                                </tr>
                            ) : (
                                movements.data.map((movement) => (
                                    <tr
                                        key={movement.id}
                                        className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                    >
                                        <td className="p-3">
                                            <FormattedDate
                                                value={movement.movement_date}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <FinishedMovementTypeBadge
                                                type={movement.type}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <FinishedMovementReasonBadge
                                                reason={movement.reason}
                                            />
                                        </td>
                                        <td className="p-3">
                                            <div className="font-medium text-foreground">
                                                {movement.product?.name ?? '-'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {movement.product?.code ?? '-'}{' '}
                                                ·{' '}
                                                {movement.product_variant
                                                    ?.presentation_label ??
                                                    movement.product_variant
                                                        ?.name ??
                                                    'Sin presentación'}
                                            </div>
                                            {movement.batch && (
                                                <div className="text-xs text-muted-foreground">
                                                    Lote #{movement.batch.id}
                                                </div>
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <div className="font-medium text-foreground">
                                                {movement.warehouse?.name ??
                                                    '-'}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                {movement.warehouse?.city ??
                                                    '-'}
                                            </div>
                                        </td>
                                        <td className="p-3 text-right font-medium text-foreground">
                                            <FormattedNumber
                                                value={movement.quantity}
                                                maxDecimals={2}
                                            />
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {movement.production_order ? (
                                                <>
                                                    <div className="text-foreground">
                                                        {
                                                            movement
                                                                .production_order
                                                                .order_number
                                                        }
                                                    </div>
                                                    <div className="text-xs">
                                                        Producción
                                                    </div>
                                                </>
                                            ) : (
                                                (movement.created_by?.name ??
                                                '-')
                                            )}
                                        </td>
                                        <td className="p-3">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                >
                                                    <Link
                                                        href={showFinishedMovement.url(
                                                            movement.id,
                                                        )}
                                                        aria-label={`Ver movimiento ${movement.id}`}
                                                    >
                                                        <Eye className="h-4 w-4" />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="flex justify-center">
                    <Pagination links={movements.links} />
                </div>
            </div>

            <Sheet
                open={drawerState.isOpen}
                onOpenChange={(open) =>
                    setDrawerState((state) => ({ ...state, isOpen: open }))
                }
                modal={false}
            >
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-2xl"
                >
                    <SheetHeader className="mb-6 flex-shrink-0">
                        <SheetTitle className="flex items-center gap-2">
                            <span
                                className={`flex h-8 w-8 items-center justify-center rounded-full ${drawer.iconClassName}`}
                            >
                                <DrawerIcon className="h-4 w-4" />
                            </span>
                            {drawer.title}
                        </SheetTitle>
                        <SheetDescription>
                            {drawer.description}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="-mr-2 flex-1 overflow-y-auto pr-2">
                        {isLoadingFormData ? (
                            <div className="space-y-5">
                                <Skeleton className="h-10 w-full" />
                                <div className="grid grid-cols-1 gap-5 md:grid-cols-2">
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                    <Skeleton className="h-10 w-full" />
                                </div>
                                <Skeleton className="h-24 w-full" />
                            </div>
                        ) : fetchError ? (
                            <div className="rounded-md border border-destructive/40 bg-destructive/10 p-4 text-sm text-destructive">
                                <p>{fetchError}</p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() => openDrawer(drawerState.mode)}
                                >
                                    Reintentar
                                </Button>
                            </div>
                        ) : batches && warehouses ? (
                            drawerState.mode === 'entry' ? (
                                <FinishedEntryMovementForm
                                    batches={batches}
                                    warehouses={warehouses}
                                    defaultWarehouseId={currentWarehouseId}
                                    onSuccess={closeDrawerAfterSuccess}
                                />
                            ) : drawerState.mode === 'exit' ? (
                                <FinishedExitMovementForm
                                    batches={batches}
                                    warehouses={warehouses}
                                    defaultWarehouseId={currentWarehouseId}
                                    onSuccess={closeDrawerAfterSuccess}
                                />
                            ) : (
                                <FinishedTransferMovementForm
                                    batches={batches}
                                    warehouses={warehouses}
                                    defaultWarehouseId={currentWarehouseId}
                                    onSuccess={closeDrawerAfterSuccess}
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
