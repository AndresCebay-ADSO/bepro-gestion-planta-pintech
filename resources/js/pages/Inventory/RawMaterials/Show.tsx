import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import RawMaterialController from '@/actions/App/Http/Controllers/Inventory/RawMaterialController';

import { ActiveBadge } from '@/components/active-badge';
import { DetailTable } from '@/components/detail-table';
import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { breadcrumbHref } from '@/lib/navigation';
import { DeleteRawMaterialDialog } from '@/pages/Inventory/RawMaterials/DeleteRawMaterialDialog';

/**
 * Tipos
 */
type InventoryBatch = {
    id: number;
    lot_number: string | null;
    supplier: string | null;
    initial_quantity: string;
    remaining_quantity: string;
    unit_price?: string;
    entry_date: string;
    expiry_date: string | null;
};

type Props = {
    returnTo?: string | null;
    rawMaterial: {
        id: number;
        code: string;
        current_price?: string | null;
        previous_price?: string | null;
        minimum_stock: string;
        alert_days_before_expiry: number;
        tracks_inventory: boolean;
        is_active: boolean;
        unit_of_measure: { id: number; name: string; symbol: string } | null;
        category: {
            id: number;
            name: string;
            code: string;
            type_label: string;
        } | null;
        inventory_batches: InventoryBatch[];
    };
    can: {
        update: boolean;
        delete: boolean;
        reactivate: boolean;
        deletePermanently: boolean;
        viewCosts: boolean;
    };
    hasAvailableStock: boolean;
    hasActivity: boolean;
};

/**
 * Raw materials show page displaying detail, batch inventory, and deletion/reactivation controls.
 */
export default function RawMaterialsShow({
    rawMaterial,
    can,
    hasAvailableStock,
    hasActivity,
}: Props) {
    // El servidor decide si «eliminar» borra o desactiva (permiso de borrado y sin historial).
    const deactivates = !can.deletePermanently;

    const totalAvailableQuantity = rawMaterial.inventory_batches.reduce(
        (sum, batch) => sum + (Number(batch.remaining_quantity) || 0),
        0,
    );

    const [deleteOpen, setDeleteOpen] = useState(false);
    const [deleteProcessing, setDeleteProcessing] = useState(false);

    const handleDeleteConfirm = () => {
        setDeleteProcessing(true);
        router.delete(RawMaterialController.destroy.url(rawMaterial.id), {
            preserveScroll: true,
            onFinish: () => {
                setDeleteProcessing(false);
                setDeleteOpen(false);
            },
        });
    };

    const handleReactivate = () => {
        router.patch(RawMaterialController.reactivate.url(rawMaterial.id));
    };

    return (
        <>
            <Head title={rawMaterial.code} />

            <div className="space-y-6 p-6">
                <PageHeader
                    title={rawMaterial.code}
                    description="Detalle de materia prima y lotes asociados."
                    actions={
                        <>
                            {can.update && (
                                <Button asChild>
                                    <Link
                                        href={RawMaterialController.edit.url(
                                            rawMaterial.id,
                                        )}
                                    >
                                        Editar
                                    </Link>
                                </Button>
                            )}

                            {can.delete && !hasAvailableStock && (
                                <Button
                                    variant={
                                        deactivates ? 'warning' : 'destructive'
                                    }
                                    onClick={() => setDeleteOpen(true)}
                                >
                                    {deactivates ? 'Desactivar' : 'Eliminar'}
                                </Button>
                            )}

                            {can.delete && hasAvailableStock && (
                                <Button variant="outline" disabled>
                                    Desactivar (Con stock en bodega)
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
                        </>
                    }
                />

                {/* Información general */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-2">
                    <InfoItem label="Código interno" value={rawMaterial.code} />

                    <InfoItem
                        label="Categoría"
                        value={rawMaterial.category?.name ?? '-'}
                    />

                    <InfoItem
                        label="Tipo de insumo"
                        value={rawMaterial.category?.type_label ?? '-'}
                    />

                    <InfoItem
                        label="Controla inventario"
                        value={
                            rawMaterial.tracks_inventory
                                ? 'Sí'
                                : 'No (consumo sin descontar saldo, a precio fijado a mano)'
                        }
                    />

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
                            <ActiveBadge
                                isActive={rawMaterial.is_active}
                                feminine
                            />
                        }
                    />

                    {can.viewCosts && (
                        <>
                            <InfoItem
                                label="Precio actual"
                                value={
                                    <FormattedNumber
                                        value={rawMaterial.current_price}
                                        currency
                                        maxDecimals={4}
                                        trimTrailingZeros
                                        emptyValue="Sin precio de referencia"
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
                        </>
                    )}

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

                {/* Lotes: el total disponible ya está en la ficha de arriba. */}
                <section className="space-y-4">
                    <h2 className="text-lg font-semibold">Lotes asociados</h2>

                    <DetailTable
                        rows={rawMaterial.inventory_batches}
                        rowKey={(batch) => batch.id}
                        caption="Lotes asociados"
                        columns={[
                            {
                                key: 'lot',
                                header: 'Lote',
                                cell: (batch) => batch.lot_number,
                                className: 'font-mono',
                            },
                            {
                                key: 'supplier',
                                header: 'Proveedor',
                                cell: (batch) => batch.supplier,
                            },
                            {
                                key: 'entry',
                                header: 'Entrada',
                                cell: (batch) => (
                                    <FormattedDate value={batch.entry_date} />
                                ),
                                className: 'whitespace-nowrap',
                            },
                            {
                                key: 'expiry',
                                header: 'Vence',
                                cell: (batch) =>
                                    batch.expiry_date ? (
                                        <FormattedDate
                                            value={batch.expiry_date}
                                        />
                                    ) : null,
                                className: 'whitespace-nowrap',
                            },
                            ...(can.viewCosts
                                ? [
                                      {
                                          key: 'price',
                                          header: 'Precio unitario',
                                          cell: (batch: InventoryBatch) => (
                                              <FormattedNumber
                                                  value={batch.unit_price}
                                                  currency
                                                  maxDecimals={4}
                                                  trimTrailingZeros
                                              />
                                          ),
                                          align: 'right' as const,
                                      },
                                  ]
                                : []),
                            {
                                key: 'initial',
                                header: 'Cantidad inicial',
                                cell: (batch) => (
                                    <FormattedNumber
                                        value={batch.initial_quantity}
                                        maxDecimals={2}
                                    />
                                ),
                                align: 'right',
                            },
                            {
                                key: 'remaining',
                                header: 'Disponible',
                                cell: (batch) => (
                                    <FormattedNumber
                                        value={batch.remaining_quantity}
                                        maxDecimals={2}
                                        bold
                                        colorize
                                    />
                                ),
                                align: 'right',
                            },
                        ]}
                        empty="No hay lotes registrados para esta materia prima."
                    />
                </section>
            </div>

            <DeleteRawMaterialDialog
                open={deleteOpen}
                onOpenChange={setDeleteOpen}
                rawMaterial={rawMaterial}
                hasActivity={hasActivity}
                deletesPermanently={can.deletePermanently}
                processing={deleteProcessing}
                onConfirm={handleDeleteConfirm}
            />
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10); el listado conserva sus filtros con `returnTo`.
RawMaterialsShow.layout = (props: Props) => ({
    breadcrumbs: [
        {
            title: 'Materias primas',
            href: breadcrumbHref(
                props.returnTo,
                RawMaterialController.index.url(),
            ),
        },
        {
            title: props.rawMaterial.code,
            href: RawMaterialController.show.url(props.rawMaterial.id),
        },
    ],
});

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
