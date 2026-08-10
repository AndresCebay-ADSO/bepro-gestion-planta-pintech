import { Head, Link, router, useForm } from '@inertiajs/react';
import { QrCode, Search } from 'lucide-react';
import type { FormEvent } from 'react';

import { TableActions } from '@/components/table-actions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { show as productionOrderShow } from '@/routes/production-orders';
import {
    index as qrCodesIndex,
    show as qrCodesShow,
} from '@/routes/qr-codes';
import type { PaginationLink } from '@/types/ui';

type QrCodeRow = {
    id: number;
    token: string;
    token_short: string;
    is_active: boolean;
    product: { id: number; name: string; code: string } | null;
    production_order: {
        id: number;
        order_number: string;
        lot_number: string | null;
    } | null;
    documents_count: number;
    created_at: string | null;
};

type Props = {
    qrCodes: {
        data: QrCodeRow[];
        links: PaginationLink[];
    };
    filters: {
        search: string;
        status: string;
    };
};

export default function QrCodesIndex({
    qrCodes,
    filters,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? 'all',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        get(qrCodesIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleStatusChange = (value: string) => {
        setData('status', value);
        router.get(
            qrCodesIndex().url,
            { ...data, status: value },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    return (
        <>
            <Head title="Códigos QR" />

            <div className="space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Códigos QR
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Consulta y gestiona los códigos QR generados para cada
                        lote de producción.
                    </p>
                </div>

                <form
                    onSubmit={handleSearch}
                    className="flex flex-col gap-3 md:flex-row md:items-end"
                >
                    <div className="flex-1">
                        <div className="relative">
                            <Search className="absolute top-2.5 left-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Buscar por producto, orden o token..."
                                className="pl-9"
                                value={data.search}
                                onChange={(e) =>
                                    setData('search', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="w-full md:w-52">
                        <Select
                            value={data.status}
                            onValueChange={handleStatusChange}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="all">Todos</SelectItem>
                                <SelectItem value="active">Activos</SelectItem>
                                <SelectItem value="inactive">
                                    Inactivos
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <Button type="submit" variant="outline">
                        <Search className="mr-2 h-4 w-4" />
                        Buscar
                    </Button>
                </form>

                <div className="overflow-hidden rounded-lg border border-border bg-card">
                    <table className="min-w-full divide-y divide-border text-sm">
                        <thead className="bg-muted/40">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Token
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Producto
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Orden / Lote
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Estado
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Docs
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Creado
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {qrCodes.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-4 py-10 text-center text-muted-foreground"
                                    >
                                        No hay códigos QR registrados.
                                    </td>
                                </tr>
                            ) : (
                                qrCodes.data.map((row) => (
                                    <tr
                                        key={row.id}
                                        className="hover:bg-muted/30"
                                    >
                                        <td className="px-4 py-3 align-top">
                                            <div className="flex items-center gap-2 font-mono text-xs text-foreground">
                                                <QrCode className="h-4 w-4 text-muted-foreground" />
                                                {row.token_short}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            {row.product ? (
                                                <div>
                                                    <p className="font-medium text-foreground">
                                                        {row.product.name}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {row.product.code}
                                                    </p>
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            {row.production_order ? (
                                                <div>
                                                    <Link
                                                        href={
                                                            productionOrderShow(
                                                                {
                                                                    production_order:
                                                                        row
                                                                            .production_order
                                                                            .id,
                                                                },
                                                            ).url
                                                        }
                                                        className="font-medium text-primary hover:underline"
                                                    >
                                                        {
                                                            row.production_order
                                                                .order_number
                                                        }
                                                    </Link>
                                                    {row.production_order
                                                        .lot_number && (
                                                        <p className="text-xs text-muted-foreground">
                                                            Lote:{' '}
                                                            {
                                                                row
                                                                    .production_order
                                                                    .lot_number
                                                            }
                                                        </p>
                                                    )}
                                                </div>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 align-top">
                                            <Badge
                                                variant={
                                                    row.is_active
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {row.is_active
                                                    ? 'Activo'
                                                    : 'Inactivo'}
                                            </Badge>
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {row.documents_count}
                                        </td>
                                        <td className="px-4 py-3 align-top text-muted-foreground">
                                            {row.created_at
                                                ? new Date(
                                                      row.created_at,
                                                  ).toLocaleDateString(
                                                      'es-CO',
                                                  )
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-right align-top">
                                            <TableActions
                                                actions={{
                                                    view: true,
                                                    edit: false,
                                                    delete: false,
                                                }}
                                                onView={() =>
                                                    router.get(
                                                        qrCodesShow({
                                                            qrCode: row.id,
                                                        }).url,
                                                    )
                                                }
                                            />
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                <Pagination links={qrCodes.links} />
            </div>
        </>
    );
}
