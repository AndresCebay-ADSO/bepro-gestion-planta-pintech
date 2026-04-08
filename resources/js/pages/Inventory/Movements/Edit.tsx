import { Head } from '@inertiajs/react';

type Props = {
    movement: { id: number; type: string };
    rawMaterials: Array<{ id: number; name: string }>;
    batches: Array<{ id: number }>;
    productionOrders: Array<{ id: number; order_number: string }>;
};

export default function InventoryMovementsEdit({ movement, rawMaterials, batches, productionOrders }: Props) {
    return (
        <>
            <Head title="Editar movimiento" />
            <div className="space-y-3 p-6">
                <h1 className="text-2xl font-semibold">Editar movimiento #{movement.id}</h1>
                <p className="text-sm text-muted-foreground">
                    Placeholder de formulario. MP: {rawMaterials.length} | Lotes: {batches.length} | OP: {productionOrders.length}
                </p>
            </div>
        </>
    );
}
