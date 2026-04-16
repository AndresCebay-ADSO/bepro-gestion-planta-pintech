import { Head, useForm, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    create as inventoryMovementsCreate,
    index as inventoryMovementsIndex,
} from '@/routes/inventory-movements';
import type { PaginationLink } from '@/types/ui';

type Props = {
    movements: {
        data: Array<{
            id: number;
            type: 'entry' | 'exit';
            quantity: string;
            movement_date: string;
            raw_material?: { code: string } | null;
            warehouse?: { name: string; city: string } | null;
        }>;
        links: PaginationLink[];
    };
    can: { create: boolean };
    filters: {
        search?: string;
    };
};

export default function InventoryMovementsIndex({
    movements,
    can,
    filters,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        get(inventoryMovementsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Movimientos de inventario" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Movimientos de Inventario
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Historial de entradas y salidas de materias primas.
                        </p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={inventoryMovementsCreate().url}>
                                Nuevo Movimiento
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form
                        onSubmit={handleSearch}
                        className="relative w-full max-w-sm"
                    >
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar por código de insumo..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="pl-10"
                        />
                    </form>
                </div>

                <div className="rounded-xl border border-border bg-card shadow-sm">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/50">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Tipo
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Bodega
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Materia Prima
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Cantidad
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.data.map((movement) => (
                                <tr
                                    key={movement.id}
                                    className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                >
                                    <td className="p-4">
                                        <FormattedDate value={movement.movement_date} />
                                    </td>
                                    <td className="p-4">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium ${
                                                movement.type === 'entry'
                                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                                    : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400'
                                            }`}
                                        >
                                            {movement.type === 'entry'
                                                ? 'ENTRADA'
                                                : 'SALIDA'}
                                        </span>
                                    </td>
                                    <td className="p-4">
                                        <div className="font-medium text-foreground">
                                            {movement.warehouse?.name}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            {movement.warehouse?.city}
                                        </div>
                                    </td>
                                    <td className="p-4 font-mono">
                                        {movement.raw_material?.code ?? '-'}
                                    </td>
                                    <td className="p-4 text-right font-medium">
                                        <FormattedNumber value={movement.quantity} maxDecimals={2} />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <div className="mt-4 flex justify-center">
                    <Pagination links={movements.links} />
                </div>
            </div>
        </>
    );
}
