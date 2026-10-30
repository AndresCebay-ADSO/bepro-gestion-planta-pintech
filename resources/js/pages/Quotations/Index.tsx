import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/pagination';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { useFilters } from '@/hooks/use-filters';
import {
    create as quotationsCreate,
    edit as quotationsEdit,
    index as quotationsIndex,
    show as quotationsShow,
} from '@/routes/quotations';
import type { Paginated } from '@/types/ui';

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
    can: {
        update: boolean;
    };
};

type Props = {
    quotations: Paginated<QuotationRow>;
    filters: Record<string, string | null | undefined>;
    can: {
        create: boolean;
        filter_by_creator: boolean;
    };
    creatorOptions: { id: number; name: string }[];
    statusOptions: { value: string; label: string }[];
};

export default function QuotationsIndex({
    quotations,
    filters,
    can,
    creatorOptions,
    statusOptions,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
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

    const columns: DataListingColumn<QuotationRow>[] = [
        {
            key: 'quotation_number',
            header: 'Número',
            cell: (quotation) => quotation.quotation_number ?? null,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'client',
            header: 'Cliente',
            cell: (quotation) => quotation.client.business_name,
            mobile: 'subtitle',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (quotation) => (
                <StatusBadge enum="QuotationStatus" value={quotation.status} />
            ),
            mobile: 'status',
        },
        {
            key: 'quotation_date',
            header: 'Fecha',
            cell: (quotation) =>
                quotation.quotation_date ? (
                    <FormattedDate
                        value={quotation.quotation_date}
                        format="short"
                    />
                ) : null,
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
        {
            key: 'total',
            header: 'Total',
            cell: (quotation) => (
                <FormattedNumber
                    value={quotation.total}
                    currency
                    maxDecimals={0}
                />
            ),
            mobile: 'primary',
            align: 'right',
        },
    ];

    return (
        <>
            <Head title="Cotizaciones" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Cotizaciones"
                    description="Gestión de ofertas comerciales."
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={quotationsCreate().url}>
                                    Nueva cotización
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
                    rows={quotations.data}
                    rowKey={(quotation) => quotation.id}
                    rowHref={(quotation) => quotationsShow(quotation.id).url}
                    rowLabel={(quotation) =>
                        `la cotización ${quotation.quotation_number ?? quotation.id}`
                    }
                    columns={columns}
                    actions={(quotation) => [
                        // El servidor decide: solo un borrador, con permiso de editar y acceso a la cotización.
                        {
                            label: 'Editar',
                            icon: Pencil,
                            href: quotationsEdit(quotation.id).url,
                            hidden: !quotation.can.update,
                        },
                    ]}
                    empty="No hay cotizaciones registradas."
                />

                <Pagination paginator={quotations} />
            </div>
        </>
    );
}
