import { Head, router, usePage } from '@inertiajs/react';
import { ArrowDownToLine, ArrowUpFromLine } from 'lucide-react';
import { useState, useEffect, useCallback, useRef } from 'react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { EntryMovementForm } from '@/components/inventory/entry-movement-form';
import { ExitMovementForm } from '@/components/inventory/exit-movement-form';
import { MovementDirectionBadge } from '@/components/movement-direction-badge';
import { PageHeader } from '@/components/page-header';
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
import { cn } from '@/lib/utils';
import {
    index as inventoryMovementsIndex,
    show as inventoryMovementsShow,
} from '@/routes/inventory-movements';
import type { InventoryOption, PaginationLink } from '@/types';

const DRAWER_FORM_PROPS = [
    'rawMaterials',
    'batches',
    'warehouses',
    'oldestEntryDate',
];

type MovementRow = {
    id: number;
    type: 'entry' | 'exit';
    quantity: string;
    movement_date: string;
    raw_material?: { code: string } | null;
    warehouse?: { name: string; city: string } | null;
};

type Props = {
    movements: {
        data: MovementRow[];
        links: PaginationLink[];
    };
    rawMaterials?: InventoryOption[];
    batches?: InventoryOption[];
    warehouses?: InventoryOption[];
    /** Fecha más vieja que admite una entrada (D1b). Llega con la recarga parcial del cajón. */
    oldestEntryDate?: string;
    warehouseOptions: { value: string; label: string }[];
    typeOptions: { value: string; label: string }[];
    can: { create: boolean };
    currentWarehouseId?: number | null;
    filters: {
        search?: string;
        type?: string;
        warehouse_id?: string | number;
        date_from?: string;
        date_to?: string;
    };
};

export default function InventoryMovementsIndex({
    movements,
    rawMaterials,
    batches,
    warehouses,
    oldestEntryDate,
    warehouseOptions,
    typeOptions,
    can,
    currentWarehouseId,
    filters,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: inventoryMovementsIndex().url,
        initialFilters: {
            search: filters.search ?? '',
            type: filters.type ?? '',
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
            placeholder: 'Buscar por materia prima o lote...',
        },
        {
            type: 'select',
            name: 'type',
            label: 'Tipo',
            options: typeOptions,
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
        mode: 'entry' | 'exit';
    }>({ isOpen: false, mode: 'entry' });
    const flash = usePage().props.flash;
    const {
        load: loadFormData,
        isLoading: isLoadingFormData,
        loadError: fetchError,
    } = useDrawerFormProps(DRAWER_FORM_PROPS);

    const openDrawer = useCallback(
        (mode: 'entry' | 'exit') => {
            setDrawerState({ isOpen: true, mode });
            loadFormData();
        },
        [loadFormData],
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

    const onSuccessForm = () => {
        setDrawerState((prev) => ({ ...prev, isOpen: false }));
        router.reload({ only: ['movements'] });
    };

    // Sin código propio: el número del movimiento (el de su detalle) lo identifica.
    const columns: DataListingColumn<MovementRow>[] = [
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
            key: 'warehouse',
            header: 'Bodega',
            cell: (movement) => (
                <>
                    <div className="font-medium text-foreground">
                        {movement.warehouse?.name}
                    </div>
                    <div className="text-xs text-muted-foreground">
                        {movement.warehouse?.city}
                    </div>
                </>
            ),
            mobile: 'meta',
        },
        {
            key: 'raw_material',
            header: 'Materia prima',
            cell: (movement) => movement.raw_material?.code ?? '-',
            mobile: 'subtitle',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'quantity',
            header: 'Cantidad',
            cell: (movement) => (
                <span
                    className={cn(
                        'font-medium',
                        movement.type === 'entry'
                            ? 'text-success-text'
                            : 'text-warning-text',
                    )}
                >
                    {movement.type === 'entry' ? '+' : '−'}
                    <FormattedNumber
                        value={movement.quantity}
                        maxDecimals={2}
                    />
                </span>
            ),
            mobile: 'meta',
            align: 'right',
        },
    ];

    return (
        <>
            <Head title="Movimientos de MP" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Movimientos de MP"
                    description="Historial de entradas y salidas de materias primas."
                    actions={
                        can.create && (
                            <>
                                <Button onClick={() => openDrawer('entry')}>
                                    <ArrowDownToLine className="mr-2 h-4 w-4" />
                                    Entrada
                                </Button>
                                <Button
                                    variant="outline"
                                    onClick={() => openDrawer('exit')}
                                >
                                    <ArrowUpFromLine className="mr-2 h-4 w-4" />
                                    Salida
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
                        inventoryMovementsShow(movement.id).url
                    }
                    columns={columns}
                    empty="No hay movimientos registrados para los filtros seleccionados."
                />

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
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-success/15 text-success-text">
                                        <ArrowDownToLine className="h-4 w-4" />
                                    </span>
                                    Registrar Entrada
                                </>
                            ) : (
                                <>
                                    <span className="flex h-8 w-8 items-center justify-center rounded-full bg-warning/15 text-warning-text">
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
                                    oldestEntryDate={oldestEntryDate}
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
