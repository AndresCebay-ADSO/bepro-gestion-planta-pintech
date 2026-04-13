import { Head, Link } from '@inertiajs/react';
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
};

export default function InventoryMovementsIndex({ movements, can }: Props) {
    return (
        <>
            <Head title="Movimientos de inventario" />
            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Movimientos de inventario</h1>
                    {can.create && (
                        <Link href="/inventory-movements/create" className="rounded bg-primary px-4 py-2 text-primary-foreground">
                            Nuevo movimiento
                        </Link>
                    )}
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
