import { Head } from '@inertiajs/react';

type Props = {
    product: {
        id: number;
        code: string;
        name: string;
        category?: { name: string } | null;
        unit_of_measure?: { name: string; symbol: string } | null;
    };
};

export default function ProductsShow({ product }: Props) {
    return (
        <>
            <Head title={`Producto ${product.code}`} />
            <div className="space-y-3 p-6">
                <h1 className="text-2xl font-semibold">{product.name}</h1>
                <p className="text-sm text-muted-foreground">Codigo: {product.code}</p>
            </div>
        </>
    );
}
