import { Head, router, usePage } from '@inertiajs/react';
import { ArrowDownToLine, ArrowLeftRight, ArrowUpFromLine } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FinishedEntryMovementForm } from '@/components/finished-inventory/finished-entry-movement-form';
import { FinishedExitMovementForm } from '@/components/finished-inventory/finished-exit-movement-form';
import { FinishedTransferMovementForm } from '@/components/finished-inventory/finished-transfer-movement-form';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { MovementDirectionBadge } from '@/components/movement-direction-badge';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Skeleton } from '@/components/ui/skeleton';
import { useDrawerFormProps } from '@/hooks/use-drawer-form-props';
import { useFilters } from '@/hooks/use-filters';
import { finishedLotLabel } from '@/lib/finished-lot';
import {
    index as finishedMovementsIndex,
    show as showFinishedMovement,
} from '@/routes/finished-inventory-movements';
import type {
    FinishedInventoryMovement,
    FinishedMovementsPage,
} from '@/types/finished-inventory';

type DrawerMode = 'entry' | 'exit' | 'transfer';

const DRAWER_FORM_PROPS = ['batches', 'warehouses', 'currentWarehouseId'];

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
        iconClassName: 'bg-success/15 text-success-text',
    },
    exit: {
        title: 'Registrar salida PT',
        description:
            'Retira unidades de un lote y valida disponibilidad antes de guardar.',
        icon: ArrowUpFromLine,
        iconClassName: 'bg-warning/15 text-warning-text',
    },
    transfer: {
        title: 'Registrar traslado PT',
        description:
            'Mueve stock de un lote entre bodegas manteniendo su trazabilidad.',
        icon: ArrowLeftRight,
        iconClassName: 'bg-info/15 text-info-text',
    },
};

export default function FinishedMovementsIndex({
    movements,
    batches,
    warehouses,
    warehouseOptions,
    typeOptions,
    reasonOptions,
    can,
    currentWarehouseId,
    filters,
}: FinishedMovementsPage) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: finishedMovementsIndex.url(),
        initialFilters: {
            search: filters.search ?? '',
            type: filters.type ?? '',
            reason: filters.reason ?? '',
            warehouse_id: filters.warehouse_id
                ? String(filters.warehouse_id)
                : '',
            date_from: filters.date_from ?? '',
            date_to: filters.date_to ?? '',
        },
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder:
                'Buscar por producto, presentación, color, lote u OP...',
        },
        {
            type: 'select',
            name: 'type',
            label: 'Tipo',
            options: typeOptions,
        },
        {
            type: 'select',
            name: 'reason',
            label: 'Motivo',
            options: reasonOptions,
        },
        {
            type: 'select',
            name: 'warehouse_id',
            label: 'Bodega',
            options: warehouseOptions,
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha',
        },
    ];

    const [drawerState, setDrawerState] = useState<{
        isOpen: boolean;
        mode: DrawerMode;
    }>({ isOpen: false, mode: 'entry' });
    const hasOpenedFromUrl = useRef(false);
    const flash = usePage().props.flash;
    const {
        load: loadFormData,
        isLoading: isLoadingFormData,
        loadError: fetchError,
    } = useDrawerFormProps(DRAWER_FORM_PROPS);

    const openDrawer = useCallback(
        (mode: DrawerMode) => {
            setDrawerState({ isOpen: true, mode });
            loadFormData();
        },
        [loadFormData],
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

    const closeDrawerAfterSuccess = () => {
        setDrawerState((state) => ({ ...state, isOpen: false }));
        router.reload({ only: ['movements'] });
    };

    // Sin código propio: el número del movimiento (el de su detalle) lo identifica.
    const columns: DataListingColumn<FinishedInventoryMovement>[] = [
        {
            key: 'number',
            header: 'Nº',
            cell: (movement) => `#${movement.id}`,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'movement_date',
            header: 'Fecha',
            cell: (movement) => (
                <FormattedDate value={movement.movement_date} />
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
        {
            key: 'type',
            header: 'Tipo',
            cell: (movement) => <MovementDirectionBadge type={movement.type} />,
            mobile: 'status',
        },
        {
            key: 'reason',
            header: 'Razón',
            cell: (movement) => (
                <StatusBadge
                    enum="FinishedInventoryMovementReason"
                    value={movement.reason}
                />
            ),
            mobile: 'meta',
        },
        {
            key: 'product',
            header: 'Producto',
            cell: (movement) => (
                <>
                    {movement.product && (
                        <div className="font-medium text-foreground">
                            {movement.product.name}
                        </div>
                    )}
                    <div className="text-xs text-muted-foreground">
                        {movement.product_variant?.presentation_label ??
                            movement.product_variant?.name ??
                            'Sin presentación'}
                    </div>
                    {movement.batch && (
                        <div className="text-xs text-muted-foreground">
                            Lote{' '}
                            {finishedLotLabel(
                                movement.batch.production_order.lot_number,
                                movement.batch.production_order.color,
                            )}
                        </div>
                    )}
                </>
            ),
            mobile: 'subtitle',
        },
        {
            key: 'warehouse',
            header: 'Bodega',
            cell: (movement) =>
                movement.warehouse ? (
                    <>
                        <div className="font-medium text-foreground">
                            {movement.warehouse.name}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            {movement.warehouse.city}
                        </div>
                    </>
                ) : null,
            mobile: 'meta',
        },
        {
            key: 'quantity',
            header: 'Cantidad',
            cell: (movement) => (
                <span
                    className={
                        movement.type === 'entry'
                            ? 'text-success-text'
                            : 'text-warning-text'
                    }
                >
                    {movement.type === 'entry' ? '+' : '−'}
                    <FormattedNumber
                        value={movement.quantity}
                        maxDecimals={2}
                    />
                </span>
            ),
            mobile: 'primary',
            align: 'right',
            // Solo la tabla: en la tarjeta manda el estilo del dato principal.
            className: 'font-medium',
        },
        {
            key: 'origin',
            header: 'Origen',
            cell: (movement) =>
                movement.production_order ? (
                    <>
                        <div className="text-foreground">
                            {movement.production_order.order_number}
                        </div>
                        <div className="text-xs">Producción</div>
                    </>
                ) : (
                    (movement.created_by?.name ?? null)
                ),
            // El principal no cuenta en los 4 datos: el origen cabe en la tarjeta.
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
    ];

    const drawer = drawerCopy[drawerState.mode];
    const DrawerIcon = drawer.icon;

    return (
        <>
            <Head title="Movimientos de PT" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Movimientos de PT"
                    description="Trazabilidad de entradas, salidas y traslados de producto terminado."
                    actions={
                        can.create && (
                            <>
                                <Button
                                    type="button"
                                    onClick={() => openDrawer('entry')}
                                >
                                    <ArrowDownToLine className="mr-2 h-4 w-4" />
                                    Entrada
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => openDrawer('exit')}
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
                            </>
                        )
                    }
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                {flash?.success && (
                    <div className="rounded-md border border-success/30 bg-success/15 px-4 py-2 text-sm text-success-text">
                        {flash.success}
                    </div>
                )}
                {flash?.error && (
                    <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-2 text-sm text-destructive">
                        {flash.error}
                    </div>
                )}

                <DataListing
                    rows={movements.data}
                    rowKey={(movement) => movement.id}
                    rowHref={(movement) =>
                        showFinishedMovement.url(movement.id)
                    }
                    columns={columns}
                    empty="No hay movimientos PT registrados con los criterios seleccionados."
                />

                <div className="mt-4 flex justify-center">
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
