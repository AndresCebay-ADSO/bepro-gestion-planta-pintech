import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import type { PaginationLink } from '@/types/ui';

type Props = {
    movements: {
        data: Array<{
            id: number;
            type: 'entrada' | 'salida';
            quantity: string;
            movement_date: string;
            raw_material?: { code: string } | null;
        }>;
        links: PaginationLink[];
    };
    can: { create: boolean };
    filters: {
        search?: string;
    };
};

export default function InventoryMovementsIndex({ movements, can, filters }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        router.get(
            '/inventory-movements',
            { search },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <>
            <Head title="Movimientos de inventario" />
            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Movimientos de Inventario</h1>
                        <p className="text-sm text-muted-foreground">Historial de entradas y salidas de materias primas.</p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href="/inventory-movements/create">Nuevo Movimiento</Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form onSubmit={handleSearch} className="relative w-full max-w-sm">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            placeholder="Buscar por código de insumo..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-10"
                        />
                    </form>
                </div>

                <div className="rounded border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border">
                            <tr>
                                <th className="p-3 text-left">Fecha</th>
                                <th className="p-3 text-left">Tipo</th>
                                <th className="p-3 text-left">Código materia prima</th>
                                <th className="p-3 text-left">Cantidad</th>
                            </tr>
                        </thead>
                        <tbody>
                            {movements.data.map((movement) => (
                                <tr key={movement.id} className="border-b border-border/50">
                                    <td className="p-3">{movement.movement_date}</td>
                                    <td className="p-3">{movement.type}</td>
                                    <td className="p-3">{movement.raw_material?.code ?? '-'}</td>
                                    <td className="p-3">{movement.quantity}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                
                <div className="flex justify-center mt-4">
                    <Pagination links={movements.links} />
                </div>
            </div>
        </>
    );
}
