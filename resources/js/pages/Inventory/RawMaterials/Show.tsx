import { Head, Link, router } from '@inertiajs/react';
import RawMaterialController from '@/actions/App/Http/Controllers/Inventory/RawMaterialController';

import { FormattedDate } from '@/components/formatted-date';
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
        reactivate: boolean;
    };
};

/**
 * Componente
 */
export default function RawMaterialsShow({ rawMaterial, can }: Props) {
    const totalAvailableQuantity = rawMaterial.inventory_batches.reduce(
        (sum, batch) => sum + (Number(batch.remaining_quantity) || 0),
        0,
    );

    const handleDelete = () => {
        if (!window.confirm('¿Estás seguro de que quieres eliminar o desactivar esta materia prima? (El sistema determinará la acción según su historial)')) {
            return;
        }

        router.delete(RawMaterialController.destroy.url(rawMaterial.code));
    };

    const handleReactivate = () => {
        router.patch(RawMaterialController.reactivate.url(rawMaterial.code));
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
                            <Link href={RawMaterialController.index.url()}>
                                Volver
                            </Link>
                        </Button>

                        {can.update && (
                            <Button asChild>
                                <Link
                                    href={RawMaterialController.edit.url(
                                        rawMaterial.code,
                                    )}
                                >
                                    Editar
                                </Link>
                            </Button>
                        )}

                        {can.delete && rawMaterial.is_active && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                Desactivar / Eliminar
                            </Button>
                        )}

                        {can.reactivate && (
                            <Button
                                variant="outline"
                                onClick={handleReactivate}
                            >
                                Reactivar
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
                            <FormattedNumber
                                value={rawMaterial.current_price}
                                currency
                                maxDecimals={4}
                                trimTrailingZeros
                            />
                        }
                    />

                    <InfoItem
                        label="Precio anterior"
                        value={
                            <FormattedNumber
                                value={rawMaterial.previous_price}
                                currency
                                maxDecimals={4}
                                trimTrailingZeros
                                emptyValue="-"
                            />
                        }
                    />

                    <InfoItem
                        label="Stock mínimo"
                        value={
                            <FormattedNumber
                                value={rawMaterial.minimum_stock}
                                maxDecimals={4}
                                trimTrailingZeros
                            />
                        }
                    />

                    <InfoItem
                        label="Alerta por vencimiento"
                        value={`${rawMaterial.alert_days_before_expiry} días`}
                    />

                    <InfoItem
                        label="Total disponible (lotes)"
                        value={
                            <FormattedNumber
                                value={totalAvailableQuantity}
                                maxDecimals={4}
                                trimTrailingZeros
                            />
                        }
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
                                <th className="p-3 text-right">
                                    Precio unitario
                                </th>
                                <th className="p-3 text-right">
                                    Cantidad inicial
                                </th>
                                <th className="p-3 text-right">Disponible</th>
                            </tr>
                        </thead>

                        <tbody>
                            {rawMaterial.inventory_batches.map((batch) => (
                                <tr
                                    key={batch.id}
                                    className="border-b border-border/60 transition last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-3 text-foreground">
                                        {batch.lot_number ?? '-'}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        {batch.supplier ?? '-'}
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        <FormattedDate value={batch.entry_date} />
                                    </td>

                                    <td className="p-3 text-muted-foreground">
                                        <FormattedDate value={batch.expiry_date} />
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={batch.unit_price}
                                            currency
                                            maxDecimals={4}
                                            trimTrailingZeros
                                        />
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={batch.initial_quantity}
                                            maxDecimals={2}
                                        />
                                    </td>

                                    <td className="p-3 text-right">
                                        <FormattedNumber
                                            value={batch.remaining_quantity}
                                            maxDecimals={2}
                                            bold
                                            colorize
                                        />
                                    </td>
                                </tr>
                            ))}

                            {rawMaterial.inventory_batches.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="p-10 text-center text-sm text-muted-foreground"
                                    >
                                        No hay lotes registrados para esta
                                        materia prima.
                                    </td>
                                </tr>
                            )}
                        </tbody>

                        {rawMaterial.inventory_batches.length > 0 && (
                            <tfoot>
                                <tr className="border-t border-border bg-muted/30">
                                    <td
                                        colSpan={6}
                                        className="p-3 text-right font-medium text-foreground"
                                    >
                                        Total Disponible
                                    </td>
                                    <td className="p-3 text-right font-medium text-foreground">
                                        <FormattedNumber
                                            value={totalAvailableQuantity}
                                            maxDecimals={4}
                                            trimTrailingZeros
                                            bold
                                        />
                                    </td>
                                </tr>
                            </tfoot>
                        )}
                    </table>
                </div>
            </div>
        </>
    );
}

/**
 * Subcomponente reutilizable (🔥 clave nivel pro)
 */
function InfoItem({ label, value }: { label: string; value: React.ReactNode }) {
    return (
        <div>
            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                {label}
            </p>
            <div className="mt-1 text-sm text-foreground">{value}</div>
        </div>
    );
}
