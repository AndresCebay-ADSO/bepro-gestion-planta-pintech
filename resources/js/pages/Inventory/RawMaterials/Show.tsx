import { Head, Link, router } from '@inertiajs/react';
import { route } from 'ziggy-js';

import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';

/**
 * Tipos
 */
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
        code: string;
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

/**
 * Componente
 */
export default function RawMaterialsShow({ rawMaterial, can }: Props) {

    const handleDelete = () => {
        if (!window.confirm('¿Estás seguro de eliminar esta materia prima?')) {
            return;
        }

        router.delete(route('raw-materials.destroy', rawMaterial.code));
    };

    return (
        <>
            <Head title={`Materia Prima ${rawMaterial.code}`} />

            <div className="space-y-6 p-6">

                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            {rawMaterial.code}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Detalle de materia prima y lotes asociados.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={route('raw-materials.index')}>
                                Volver
                            </Link>
                        </Button>

                        {can.update && (
                            <Button asChild>
                                <Link href={route('raw-materials.edit', rawMaterial.code)}>
                                    Editar
                                </Link>
                            </Button>
                        )}

                        {can.delete && (
                            <Button variant="destructive" onClick={handleDelete}>
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Información general */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-2">

                    <InfoItem label="Código interno" value={rawMaterial.code} />

                    <InfoItem
                        label="Unidad"
                        value={
                            rawMaterial.unit_of_measure
                                ? `${rawMaterial.unit_of_measure.name} (${rawMaterial.unit_of_measure.symbol})`
                                : '-'
                        }
                    />

                    <InfoItem
                        label="Estado"
                        value={
                            <span
                                className={
                                    rawMaterial.is_active
                                        ? 'rounded-full bg-emerald-500/15 px-2 py-1 text-xs font-medium text-emerald-600 dark:text-emerald-300'
                                        : 'rounded-full bg-slate-500/15 px-2 py-1 text-xs font-medium text-slate-600 dark:text-slate-300'
                                }
                            >
                                {rawMaterial.is_active ? 'Activa' : 'Inactiva'}
                            </span>
                        }
                    />

                    <InfoItem
                        label="Precio actual"
                        value={
                            <FormattedNumber value={rawMaterial.current_price} currency />
                        }
                    />

                    <InfoItem
                        label="Precio anterior"
                        value={
                            <FormattedNumber
                                value={rawMaterial.previous_price}
                                currency
                                emptyValue="-"
                            />
                        }
                    />

                    <InfoItem
                        label="Stock mínimo"
                        value={
                            <FormattedNumber value={rawMaterial.minimum_stock} />
                        }
                    />

                    <InfoItem
                        label="Alerta por vencimiento"
                        value={`${rawMaterial.alert_days_before_expiry} días`}
                    />
                </div>

                {/* Lotes */}
                <div className="overflow-x-auto rounded-lg border border-border bg-card">

                    <div className="border-b border-border px-4 py-3">
                        <h2 className="font-medium text-foreground">
                            Lotes asociados
                        </h2>
                    </div>

                    <table className="w-full text-sm">

                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-3 text-left">Lote</th>
                                <th className="p-3 text-left">Proveedor</th>
                                <th className="p-3 text-left">Entrada</th>
                                <th className="p-3 text-left">Vence</th>
                                <th className="p-3 text-right">Cantidad inicial</th>
                                <th className="p-3 text-right">Disponible</th>
                            </tr>
                        </thead>

                        <tbody>
                            {rawMaterial.inventory_batches.map((batch) => (
                                <tr
                                    key={batch.id}
                                    className="border-b border-border/60 last:border-0 hover:bg-muted/30 transition"
                                >
                                    <td className="p-3 text-foreground">
                                        {batch.lot_number ?? '-'}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {batch.supplier ?? '-'}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {batch.entry_date}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {batch.expiry_date ?? '-'}
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber value={batch.initial_quantity} />
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={batch.remaining_quantity}
                                            bold
                                            colorize
                                        />
                                    </td>
                                </tr>
                            ))}

                            {rawMaterial.inventory_batches.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="p-10 text-center text-sm text-muted-foreground">
                                        No hay lotes registrados para esta materia prima.
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

/**
 * Subcomponente reutilizable (🔥 clave nivel pro)
 */
function InfoItem({
    label,
    value,
}: {
    label: string;
    value: React.ReactNode;
}) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">
                {label}
            </p>
            <div className="text-sm text-foreground mt-1">
                {value}
            </div>
        </div>
    );
}