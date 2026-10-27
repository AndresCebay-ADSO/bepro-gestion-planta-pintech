import { Head, Link } from '@inertiajs/react';
import { Pencil } from 'lucide-react';
import type { ComponentProps } from 'react';

import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { PageHeader } from '@/components/page-header';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import {
    create as requestsCreate,
    edit as requestsEdit,
    index as requestsIndex,
    show as requestsShow,
} from '@/routes/paint-development-requests';
import type { PaginationLink } from '@/types/ui';

type RequestRow = {
    id: number;
    request_number: number;
    client_name: string | null;
    creator?: { name: string } | null;
    status: string;
    status_label: string;
    project_name: string;
    sample_due_date: string | null;
    city: string;
    created_at: string;
};

type Props = {
    requests: {
        data: RequestRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    statusOptions: { value: string; label: string }[];
    can: {
        create: boolean;
    };
};

export default function PaintDevelopmentRequestsIndex({
    requests,
    filters,
    statusOptions,
    can,
}: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: requestsIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Número, proyecto o cliente...',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
        {
            type: 'date-range',
            nameFrom: 'date_from',
            nameTo: 'date_to',
            label: 'Fecha muestra',
        },
    ];

    const columns: DataListingColumn<RequestRow>[] = [
        {
            key: 'request_number',
            header: 'Número',
            cell: (req) => req.request_number,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'project_name',
            header: 'Proyecto',
            cell: (req) => (
                <span
                    className="block md:max-w-xs md:truncate"
                    title={req.project_name}
                >
                    {req.project_name}
                </span>
            ),
            mobile: 'subtitle',
        },
        {
            key: 'client',
            header: 'Cliente',
            cell: (req) => req.client_name ?? '—',
            mobile: 'meta',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (req) => (
                <StatusBadge
                    enum="PaintDevelopmentRequestStatus"
                    value={req.status}
                />
            ),
            mobile: 'status',
        },
        {
            key: 'sample_due_date',
            header: 'Fecha muestra',
            cell: (req) => (
                <FormattedDate
                    value={req.sample_due_date}
                    format="short"
                    emptyValue="—"
                />
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap',
        },
    ];

    return (
        <>
            <Head title="Desarrollo de pinturas" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Desarrollo de pinturas"
                    description="Solicitudes de desarrollo de nuevos productos"
                    actions={
                        can.create && (
                            <Button asChild>
                                <Link href={requestsCreate().url}>
                                    Nueva solicitud
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
                    rows={requests.data}
                    rowKey={(req) => req.id}
                    rowHref={(req) => requestsShow(req.id).url}
                    rowLabel={(req) => `la solicitud ${req.request_number}`}
                    columns={columns}
                    actions={(req) => [
                        // Solo un borrador se puede editar.
                        {
                            label: 'Editar',
                            icon: Pencil,
                            href: requestsEdit(req.id).url,
                            hidden: req.status !== 'draft',
                        },
                    ]}
                    empty="No hay solicitudes registradas."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={requests.links} />
                </div>
            </div>
        </>
    );
}
