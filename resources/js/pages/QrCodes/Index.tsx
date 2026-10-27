import { Head } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { ActiveBadge } from '@/components/active-badge';
import { DataListing } from '@/components/data-listing';
import type { DataListingColumn } from '@/components/data-listing';
import { DataTableFilters } from '@/components/data-table-filters';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import Pagination from '@/components/ui/pagination';
import { useFilters } from '@/hooks/use-filters';
import { index as qrCodesIndex, show as qrCodesShow } from '@/routes/qr-codes';
import type { PaginationLink } from '@/types/ui';

type QrCodeRow = {
    id: number;
    token: string;
    token_short: string;
    is_active: boolean;
    product: {
        id: number;
        name: string;
        code: string;
        /** Nombre con el color de la orden (3.4). */
        display_name: string;
    } | null;
    production_order: {
        id: number;
        order_number: string;
        lot_number: number;
    } | null;
    documents_count: number;
    created_at: string | null;
};

type Props = {
    qrCodes: {
        data: QrCodeRow[];
        links: PaginationLink[];
    };
    filters: Record<string, string | null | undefined>;
    can?: {
        viewAny: boolean;
        update: boolean;
    };
};

const statusOptions = [
    { value: 'active', label: 'Activos' },
    { value: 'inactive', label: 'Inactivos' },
];

export default function QrCodesIndex({ qrCodes, filters }: Props) {
    const {
        filters: filterState,
        setFilter,
        setFilterImmediate,
        clearFilters,
    } = useFilters({
        routeUrl: qrCodesIndex().url,
        initialFilters: filters,
    });

    const filterFields: ComponentProps<typeof DataTableFilters>['fields'] = [
        {
            type: 'text',
            name: 'search',
            label: 'Buscar',
            placeholder: 'Buscar por producto, color, OP, lote o token…',
        },
        {
            type: 'select',
            name: 'status',
            label: 'Estado',
            options: statusOptions,
        },
    ];

    const columns: DataListingColumn<QrCodeRow>[] = [
        {
            key: 'token',
            header: 'Token',
            cell: (row) => row.token_short,
            mobile: 'title',
            className: 'font-mono',
            cardClassName: 'font-mono',
        },
        {
            key: 'product',
            header: 'Producto',
            cell: (row) => row.product?.display_name ?? '—',
            mobile: 'subtitle',
        },
        {
            key: 'order',
            header: 'Orden / Lote',
            // Solo el token es enlace (DESIGN.md §9): la OP se abre desde el detalle del QR.
            cell: (row) =>
                row.production_order ? (
                    <>
                        <div className="font-mono">
                            {row.production_order.order_number}
                        </div>
                        <div className="text-xs text-muted-foreground">
                            Lote: {row.production_order.lot_number}
                        </div>
                    </>
                ) : (
                    '—'
                ),
            mobile: 'meta',
        },
        {
            key: 'status',
            header: 'Estado',
            cell: (row) => <ActiveBadge isActive={row.is_active} />,
            mobile: 'status',
        },
        {
            key: 'documents',
            header: 'Documentos',
            cell: (row) => <FormattedNumber value={row.documents_count} />,
            mobile: 'meta',
            align: 'right',
            className: 'text-muted-foreground',
        },
        {
            key: 'created_at',
            header: 'Creado',
            cell: (row) => (
                <FormattedDate
                    value={row.created_at}
                    format="short"
                    emptyValue="—"
                />
            ),
            mobile: 'meta',
            className: 'whitespace-nowrap text-muted-foreground',
        },
    ];

    return (
        <>
            <Head title="Códigos QR" />

            <div className="space-y-6 p-6">
                <PageHeader
                    title="Códigos QR"
                    description="Consulta y gestiona los códigos QR generados para cada lote de producción."
                />

                <DataTableFilters
                    fields={filterFields}
                    filters={filterState}
                    onFilter={setFilter}
                    onFilterImmediate={setFilterImmediate}
                    onClear={clearFilters}
                />

                <DataListing
                    rows={qrCodes.data}
                    rowKey={(row) => row.id}
                    rowHref={(row) => qrCodesShow({ qrCode: row.id }).url}
                    columns={columns}
                    empty="No hay códigos QR registrados."
                />

                <div className="mt-4 flex justify-center">
                    <Pagination links={qrCodes.links} />
                </div>
            </div>
        </>
    );
}
