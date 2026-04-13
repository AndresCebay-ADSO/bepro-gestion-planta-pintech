import { Head, Link } from '@inertiajs/react';
import Pagination from '@/components/ui/pagination';
import type { PaginationLink } from '@/types/ui';

type Props = {
    products: {
        data: Array<{
            id: number;
            code: string;
            name: string;
            category?: { id: number; name: string } | null;
            unit_of_measure?: { id: number; name: string; symbol: string } | null;
        }>;
        links: PaginationLink[];
    };
    can: {
        create: boolean;
        managePrices: boolean;
    };
};

export default function ProductsIndex({ products, can }: Props) {
    return (
        <>
            <Head title="Productos" />

            <div className="space-y-4 p-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-semibold">Productos</h1>
                    {can.create && (
                        <Link href="/products/create" className="rounded bg-primary px-4 py-2 text-primary-foreground">
                            Nuevo producto
                        </Link>
                    )}
                </div>

                <div className="rounded border border-border bg-card">
                    <table className="w-full text-sm">
                        <thead className="border-b border-border">
                            <tr>
                                <th className="p-3 text-left">Codigo</th>
                                <th className="p-3 text-left">Nombre</th>
                                <th className="p-3 text-left">Categoria</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((product) => (
                                <tr key={product.id} className="border-b border-border/50">
                                    <td className="p-3">{product.code}</td>
                                    <td className="p-3">{product.name}</td>
                                    <td className="p-3">{product.category?.name ?? '-'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                
                <div className="flex justify-center mt-4">
                    <Pagination links={products.links} />
                </div>
            </div>
        </>
    );
}
