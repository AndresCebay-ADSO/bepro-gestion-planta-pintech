import { Head } from '@inertiajs/react';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean };
};

export default function ProductsCreate({ categories, units }: Props) {
    return (
        <>
            <Head title="Crear producto" />
            <div className="space-y-3 p-6">
                <h1 className="text-2xl font-semibold">Crear producto</h1>
                <p className="text-sm text-muted-foreground">
                    Placeholder de formulario. Categorias: {categories.length} | Unidades: {units.length}
                </p>
            </div>
        </>
    );
}
