import { Head, Link, router } from '@inertiajs/react';
import { BadgeDollarSign } from 'lucide-react';
import type { ComponentProps } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { withReturnTo } from '@/lib/navigation';
import {
    index as formulasIndex,
    create as formulasCreate,
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
    can: { create: boolean };
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

    return (
        <>
            <Head title="Fórmulas" />
            <div className="space-y-4 p-6">
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

                <div className="rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Producto
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Versión
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Notas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Creada
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {formulas.data.map((formula) => (
                                <tr
                                    key={formula.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4">
                                        <div className="font-medium text-foreground">
                                            {formula.product?.name ?? '-'}
                                        </div>
                                    </td>
                                    <td className="p-4">
                                        <div className="font-mono">
                                            v{formula.version}
                                        </div>
                                        {formula.name && (
                                            <div className="text-xs text-muted-foreground">
                                                {formula.name}
                                            </div>
                                        )}
                                    </td>
                                    <td className="p-4">
                                        <div className="flex flex-wrap gap-1.5">
                                            <ActiveBadge
                                                isActive={formula.is_active}
                                                feminine
                                            />
                                            {formula.is_price_reference && (
                                                <Badge variant="outline">
                                                    <BadgeDollarSign aria-hidden="true" />
                                                    Referencia de precio
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="max-w-xs truncate p-4 text-muted-foreground">
                                        {formula.notes ?? '-'}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        <FormattedDate
                                            value={formula.created_at}
                                            format="datetime"
                                        />
                                    </td>
                                    <td className="p-4 text-right">
                                        <TableActions
                                            actions={{
                                                view: true,
                                                edit: false,
                                                // La de referencia no se elimina (se elige otra antes).
                                                delete: !formula.is_price_reference,
                                            }}
                                            onView={() =>
                                                router.get(
                                                    withReturnTo(
                                                        formulasShow({
                                                            formula: formula.id,
                                                        }).url,
                                                    ),
                                                )
                                            }
                                            onDelete={() => {
                                                if (
                                                    window.confirm(
                                                        `¿Eliminar la fórmula v${formula.version} de ${formula.product?.name}?`,
                                                    )
                                                ) {
                                                    router.delete(
                                                        `/formulas/${formula.id}`,
                                                    );
                                                }
                                            }}
                                        />
                                    </td>
                                </tr>
                            ))}
                            {formulas.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay fórmulas registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={formulas.links} />
                </div>
            </div>
        </>
    );
}
