import { Head, Link, router } from '@inertiajs/react';
import { FileText, Plus } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedNumber } from '@/components/formatted-number';
import { TableActions } from '@/components/table-actions';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as quotationsCreate,
    edit as quotationsEdit,
    index as quotationsIndex,
    show as quotationsShow,
} from '@/routes/quotations';
import type { PaginationLink } from '@/types/ui';

type QuotationRow = {
    id: number;
    quotation_number: number | null;
    client: { id: number; business_name: string };
    creator?: { name: string } | null;
    status: string;
    status_label: string;
    quotation_date: string | null;
    total: number;
    items_count: number;
    created_at: string;
};

type Props = {
    quotations: {
        data: QuotationRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
        filter_by_creator: boolean;
    };
    creatorOptions: { id: number; name: string }[];
    statusOptions: { value: string; label: string }[];
};

const statusColors: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    sent: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    accepted:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};

export default function QuotationsIndex({
    quotations,
    filters,
    can,
    creatorOptions,
    statusOptions,
}: Props) {
    const { filters: filterState, setFilter, setFilterImmediate, clearFilters } =
        useFilters({
            routeUrl: quotationsIndex().url,
            initialFilters: filters,
        });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Número, cliente o NIT...',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
    ];

    if (can.filter_by_creator) {
        filterFields.push({
            type: 'select',
            name: 'created_by',
            label: 'Creado por',
            options: [
                ...creatorOptions.map((u) => ({
                    value: String(u.id),
                    label: u.name,
                })),
            ],
        });
    }

    filterFields.push({
        type: 'date-range',
        nameFrom: 'date_from',
        nameTo: 'date_to',
        label: 'Fecha de cotización',
    });

    return (
        <>
            <Head title="Cotizaciones" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Cotizaciones
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión de ofertas comerciales
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={quotationsCreate().url}>
                                <Plus className="mr-2 h-4 w-4" />
                                Nueva cotización
                            </Link>
                        </Button>
                    )}
                </div>

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onChange={(name, value) => {
                        if (name === 'search') {
                            setFilter(name, value);
                        } else {
                            setFilterImmediate(name, value);
                        }
                    }}
                    onClear={clearFilters}
                />

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/30">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Número
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Cliente
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Estado
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Total
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {quotations.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        <FileText className="mx-auto mb-2 h-8 w-8 opacity-40" />
                                        No hay cotizaciones registradas.
                                    </td>
                                </tr>
                            ) : (
                                quotations.data.map((quotation) => (
                                    <tr
                                        key={quotation.id}
                                        className="border-b border-border last:border-0"
                                    >
                                        <td className="px-4 py-3 font-mono text-xs">
                                            {quotation.quotation_number ?? '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {quotation.client.business_name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${statusColors[quotation.status] ?? ''}`}
                                            >
                                                {quotation.status_label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-3">
                                            {quotation.quotation_date ?? '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right">
                                            <FormattedNumber
                                                value={quotation.total}
                                                currency
                                                maxDecimals={0}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <TableActions
                                                actions={{
                                                    view: true,
                                                    edit:
                                                        quotation.status ===
                                                        'draft',
                                                    delete: false,
                                                }}
                                                onView={() =>
                                                    router.get(
                                                        quotationsShow(
                                                            quotation.id,
                                                        ).url,
                                                    )
                                                }
                                                onEdit={
                                                    quotation.status === 'draft'
                                                        ? () =>
                                                              router.get(
                                                                  quotationsEdit(
                                                                      quotation.id,
                                                                  ).url,
                                                              )
                                                        : undefined
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={quotations.links} />
            </div>
        </>
    );
}
