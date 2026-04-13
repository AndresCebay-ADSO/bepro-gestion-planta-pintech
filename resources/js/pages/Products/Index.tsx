import { Head, useForm, Link } from '@inertiajs/react';
import { Search } from 'lucide-react';
import type { FormEvent } from 'react';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import {
    create as productsCreate,
    index as productsIndex,
} from '@/routes/products';
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
    filters: {
        search?: string;
    };
};

export default function ProductsIndex({ products, can, filters }: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const handleSearch = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        get(productsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    return (
        <>
            <Head title="Productos" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">Productos</h1>
                        <p className="text-sm text-muted-foreground">Gestión del catálogo de productos y precios.</p>
                    </div>
                    {can.create && (
                        <Button asChild>
                            <Link href={productsCreate().url}>
                                Nuevo Producto
                            </Link>
                        </Button>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <form onSubmit={handleSearch} className="relative w-full max-w-sm">
                        <Search className="text-muted-foreground absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2" />
                        <Input
                            placeholder="Buscar producto..."
                            value={data.search}
                            onChange={(e) => setData('search', e.target.value)}
                            className="pl-10"
                        />
                    </form>
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
