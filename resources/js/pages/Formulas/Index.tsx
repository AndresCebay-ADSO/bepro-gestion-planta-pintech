import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign, Trash2 } from 'lucide-react';
import type { ComponentProps } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import {
    index as formulasIndex,
    create as formulasCreate,
    destroy as formulasDestroy,
    show as formulasShow,
} from '@/routes/formulas';
import type { PaginationLink } from '@/types/ui';

type FormulaItem = {
    id: number;
    version: number;
    name: string | null;
    is_active: boolean;
    is_price_reference: boolean;
    notes: string | null;
    created_at: string;
    product?: { id: number; name: string } | null;
    created_by?: { name: string } | null;
};

type Props = {
    formulas: {
        data: FormulaItem[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: { create: boolean; delete: boolean };
};

const statusOptions = [
    { value: 'active', label: 'Activas' },
    { value: 'inactive', label: 'Inactivas' },
];

export default function FormulasIndex({ formulas, filters, can }: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: formulasIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por producto o nombre de la fórmula…',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
    ];

    const columns: DataListingColumn<FormulaItem>[] = [
        {
            key: 'product',
            header: 'Producto',
            cell: (formula) => formula.product?.name ?? '-',
            mobile: 'title',
        },
        {
            key: 'version',
            header: 'Versión',
            cell: (formula) => (
                <>
                    <div className="font-mono">v{formula.version}</div>
                    {formula.name && (
                        <div className="text-xs text-muted-foreground">
                            {formula.name}
                        </div>
                    )}
                </>
            ),
            mobile: 'subtitle',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (formula) => (
                <div className="flex flex-wrap gap-1.5">
                    <ActiveBadge isActive={formula.is_active} feminine />
                    {formula.is_price_reference && (
                        <Badge variant="outline">
                            <BadgeDollarSign aria-hidden="true" />
                            Referencia de precio
                        </Badge>
                    )}
                </div>
            ),
            mobile: 'status',
        },
        {
            key: 'notes',
            header: 'Notas',
            cell: (formula) => (
                <span
                    className="block md:max-w-xs md:truncate"
                    title={formula.notes ?? undefined}
                >
                    {formula.notes ?? '-'}
                </span>
            ),
            mobile: 'hidden',
            className: 'text-muted-foreground',
        },
        {
            key: 'created_at',
            header: 'Creada',
            cell: (formula) => (
                <FormattedDate value={formula.created_at} format="datetime" />
            ),
            mobile: 'meta',
            className: 'text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Fórmulas" />
            <div className="space-y-6 p-6">
                <PageHeader
                    title="Fórmulas"
                    description="Un producto puede tener varias fórmulas activas para producir; la de referencia de precio define su costo."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={withReturnTo(formulasCreate().url)}>
                                    Nueva fórmula
                                </Link>
                            </Button>
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

                <DataListing
                    rows={formulas.data}
                    rowKey={(formula) => formula.id}
                    rowHref={(formula) =>
                        withReturnTo(formulasShow({ formula: formula.id }).url)
                    }
                    rowLabel={(formula) =>
                        `${formula.product?.name ?? 'fórmula'} v${formula.version}`
                    }
                    columns={columns}
                    actions={(formula) => [
                        {
                            label: 'Eliminar',
                            icon: Trash2,
                            destructive: true,
                            onSelect: () => {
                                if (
                                    window.confirm(
                                        `¿Eliminar la fórmula v${formula.version} de ${formula.product?.name}?`,
                                    )
                                ) {
                                    router.delete(
                                        formulasDestroy({ formula: formula.id })
                                            .url,
                                    );
                                }
                            },
                            // La de referencia no se elimina: se elige otra antes.
                            disabledReason: formula.is_price_reference
                                ? 'No disponible: es la fórmula de referencia de precio. Elige otra antes.'
                                : undefined,
                            hidden: !can.delete,
                        },
                    ]}
                    empty="No hay fórmulas registradas."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={formulas.links} />
                </div>
            </div>
        </>
    );
}
