import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { MovementForm } from '@/components/inventory/movement-form';
import { Button } from '@/components/ui/button';

type Option = {
    id: number;
    name?: string;
    code?: string;
    lot_number?: string;
    order_number?: string;
    city?: string;
    type?: string;
};

type Props = {
    rawMaterials: Option[];
    batches: Option[];
    warehouses: Option[];
    productionOrders: Option[];
};

type MovementFormData = {
    raw_material_id: string;
    warehouse_id: string;
    batch_id: string;
    production_order_id: string;
    type: string;
    quantity: string;
    cost_price: string;
    movement_date: string;
    notes: string;
};

export default function InventoryMovementsCreate({
    rawMaterials,
    batches,
    warehouses,
    productionOrders,
}: Props) {
    const form = useForm<MovementFormData>({
        raw_material_id: '',
        warehouse_id: '',
        batch_id: '',
        production_order_id: '',
        type: 'entrada',
        quantity: '',
        cost_price: '',
        movement_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            raw_material_id: Number(data.raw_material_id),
            warehouse_id: Number(data.warehouse_id),
            batch_id: data.batch_id === '' ? null : Number(data.batch_id),
            production_order_id:
                data.production_order_id === ''
                    ? null
                    : Number(data.production_order_id),
            quantity: Number(data.quantity),
            cost_price: Number(data.cost_price),
        }));

        form.post(route('inventory-movements.store'));
    };

    return (
        <>
            <Head title="Registrar Movimiento" />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Registrar Movimiento de Inventario
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Documenta una entrada o salida de materia prima en una
                        bodega específica.
                    </p>
                </div>

                <div className="rounded-xl border bg-card p-8 shadow-sm transition-all hover:shadow-md">
                    <MovementForm
                        form={form}
                        rawMaterials={rawMaterials}
                        batches={batches}
                        warehouses={warehouses}
                        productionOrders={productionOrders}
                        onSubmit={submit}
                        submitLabel="Registrar Movimiento"
                    />

                    <div className="mt-6 border-t pt-6">
                        <Button
                            variant="ghost"
                            asChild
                            className="text-muted-foreground hover:text-foreground"
                        >
                            <Link href={route('inventory-movements.index')}>
                                ← Volver al listado
                            </Link>
                        </Button>
                    </div>
                </div>
            </div>
        </>
    );
}
