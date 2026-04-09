import { Head } from '@inertiajs/react';

type Props = {
    rawMaterials: Array<{ id: number; code: string }>;
    batches: Array<{ id: number }>;
    productionOrders: Array<{ id: number; order_number: string }>;
};

export default function InventoryMovementsCreate({ rawMaterials, batches, productionOrders }: Props) {
    return (
        <>
            <Head title="Crear movimiento de inventario" />
            <div className="space-y-3 p-6">
                <h1 className="text-2xl font-semibold">Crear movimiento</h1>
                <p className="text-sm text-muted-foreground">
                    Placeholder de formulario. MP: {rawMaterials.length} | Lotes: {batches.length} | OP: {productionOrders.length}
                </p>
            </div>
        </>
    );
}
