import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';
import { Button } from '@/components/ui/button';

type InventoryBatch = {
    id: number;
    lot_number: string | null;
    supplier: string | null;
    initial_quantity: string;
    remaining_quantity: string;
    unit_price: string;
    entry_date: string;
    expiry_date: string | null;
};

type Props = {
    rawMaterial: {
        id: number;
        name: string;
        current_price: string;
        previous_price: string | null;
        minimum_stock: string;
        alert_days_before_expiry: number;
        is_active: boolean;
        unit_of_measure: { id: number; name: string; symbol: string } | null;
        inventory_batches: InventoryBatch[];
    };
    can: {
        update: boolean;
        delete: boolean;
    };
};

export default function RawMaterialsShow({ rawMaterial, can }: Props) {
    const handleDelete = () => {
        if (!window.confirm('¿Estás seguro de eliminar esta materia prima?')) {
            return;
        }

        router.delete(route('raw-materials.destroy', rawMaterial.id));
    };

    return (
        <>
            <Head title={`Materia Prima ${rawMaterial.name}`} />

            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">{rawMaterial.name}</h1>
                        <p className="text-sm text-muted-foreground">Detalle de materia prima y lotes asociados.</p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('raw-materials.index')}>Volver</Link>
                        </Button>
                        {can.update && (
                            <Button asChild>
                                <Link href={route('raw-materials.edit', rawMaterial.id)}>Editar</Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button variant="destructive" onClick={handleDelete}>
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-2">
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Unidad</p>
                        <p className="text-sm text-foreground">{rawMaterial.unit_of_measure ? `${rawMaterial.unit_of_measure.name} (${rawMaterial.unit_of_measure.symbol})` : '-'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Estado</p>
                        <p className="text-sm text-foreground">{rawMaterial.is_active ? 'Activa' : 'Inactiva'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Precio actual</p>
                        <p className="text-sm text-foreground">${rawMaterial.current_price}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Precio anterior</p>
                        <p className="text-sm text-foreground">{rawMaterial.previous_price ? `$${rawMaterial.previous_price}` : '-'}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Stock mínimo</p>
                        <p className="text-sm text-foreground">{rawMaterial.minimum_stock}</p>
                    </div>
                    <div>
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">Alerta por vencimiento</p>
                        <p className="text-sm text-foreground">{rawMaterial.alert_days_before_expiry} días</p>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-lg border border-border bg-card">
                    <div className="border-b border-border px-4 py-3">
                        <h2 className="font-medium text-foreground">Lotes asociados</h2>
                    </div>
                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left font-medium text-foreground">Lote</th>
                                <th className="p-3 text-left font-medium text-foreground">Proveedor</th>
                                <th className="p-3 text-left font-medium text-foreground">Entrada</th>
                                <th className="p-3 text-left font-medium text-foreground">Vence</th>
                                <th className="p-3 text-left font-medium text-foreground">Cantidad inicial</th>
                                <th className="p-3 text-left font-medium text-foreground">Cantidad restante</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rawMaterial.inventory_batches.map((batch) => (
                                <tr key={batch.id} className="border-b border-border/60 last:border-0">
                                    <td className="p-3 text-foreground">{batch.lot_number ?? '-'}</td>
                                    <td className="p-3 text-muted-foreground">{batch.supplier ?? '-'}</td>
                                    <td className="p-3 text-muted-foreground">{batch.entry_date}</td>
                                    <td className="p-3 text-muted-foreground">{batch.expiry_date ?? '-'}</td>
                                    <td className="p-3 text-muted-foreground">{batch.initial_quantity}</td>
                                    <td className="p-3 text-muted-foreground">{batch.remaining_quantity}</td>
                                </tr>
                            ))}
                            {rawMaterial.inventory_batches.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-8 text-center text-sm text-muted-foreground">
                                        Esta materia prima no tiene lotes registrados.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
