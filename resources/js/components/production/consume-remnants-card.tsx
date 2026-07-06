import { router } from '@inertiajs/react';
import { FlaskConical } from 'lucide-react';
import { useState } from 'react';

import { store as consumeRemnant } from '@/actions/App/Http/Controllers/Production/RemnantConsumptionController';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ProductionOrder } from '@/types/production-orders';

type ConsumeRemnantsCardProps = {
    orderId: number;
    order: ProductionOrder;
    canConsume: boolean;
};

export function ConsumeRemnantsCard({
    orderId,
    order,
    canConsume,
}: ConsumeRemnantsCardProps) {
    const consumedRemnants = order.remnant_consumptions ?? [];
    const availableRemnants = order.available_remnants ?? [];

    const [submitting, setSubmitting] = useState(false);
    const [selectedRemnantId, setSelectedRemnantId] = useState<
        string | number | null
    >(null);
    const [quantityGallons, setQuantityGallons] = useState('');
    const [notes, setNotes] = useState('');
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const comboboxOptions = availableRemnants.map((remnant) => ({
        id: remnant.id,
        label: `${remnant.source_order_number} — ${remnant.available_quantity_gallons} gal disponibles`,
    }));

    const activeRemnant = availableRemnants.find(
        (r) => r.id === selectedRemnantId,
    );

    const handleAdd = () => {
        if (!selectedRemnantId || !quantityGallons) {
            const errors: Record<string, string> = {};

            if (!selectedRemnantId) {
                errors.remnant_id = 'Seleccione un saldo.';
            }

            if (!quantityGallons) {
                errors.quantity_gallons = 'Ingrese la cantidad.';
            }

            setFormErrors(errors);

            return;
        }

        setSubmitting(true);
        setFormErrors({});

        router.post(
            consumeRemnant({ production_order: orderId }).url,
            {
                remnant_id: selectedRemnantId,
                quantity_gallons: Number(quantityGallons),
                notes: notes || undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedRemnantId(null);
                    setQuantityGallons('');
                    setNotes('');
                },
                onError: (errors) => {
                    setFormErrors(errors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    if (!canConsume && consumedRemnants.length === 0 && !order.remnant) {
        return null;
    }

    return (
        <Card className="border-emerald-200 shadow-sm dark:border-emerald-900/50">
            <CardHeader className="bg-emerald-50/50 pb-4 dark:bg-emerald-950/20">
                <CardTitle className="flex items-center gap-2 text-base text-emerald-800 dark:text-emerald-300">
                    <FlaskConical className="h-4 w-4" />
                    Saldos de Producto Terminado
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4 pt-4">
                {order.remnant && (
                    <div className="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                        <p className="text-sm font-medium text-emerald-800 dark:text-emerald-300">
                            Esta orden generó un saldo de{' '}
                            <FormattedNumber
                                value={order.remnant.original_quantity_gallons}
                                maxDecimals={4}
                            />{' '}
                            gal (
                            <FormattedNumber
                                value={order.remnant.original_quantity_kg}
                                maxDecimals={4}
                            />{' '}
                            kg)
                        </p>
                        <p className="mt-0.5 text-xs text-emerald-600 dark:text-emerald-400">
                            Estado: {order.remnant.status_label} &middot;
                            Disponible:{' '}
                            <FormattedNumber
                                value={order.remnant.available_quantity_gallons}
                                maxDecimals={4}
                            />{' '}
                            gal
                        </p>
                    </div>
                )}

                {consumedRemnants.length > 0 && (
                    <div className="space-y-3">
                        <Label className="flex items-center gap-1.5">
                            Saldos Consumidos en esta Orden
                        </Label>
                        <div className="overflow-hidden rounded-md border">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="border-b bg-emerald-50 dark:bg-emerald-950/20">
                                        <tr>
                                            <th className="p-3 text-left">
                                                Origen
                                            </th>
                                            <th className="p-3 text-right">
                                                Galones
                                            </th>
                                            <th className="p-3 text-right">
                                                Kilogramos
                                            </th>
                                            <th className="p-3 text-right">
                                                Costo
                                            </th>
                                            <th className="p-3 text-left">
                                                Operario
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {consumedRemnants.map((consumption) => (
                                            <tr
                                                key={consumption.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="p-3 font-medium">
                                                    {consumption.source_order_number ??
                                                        'Desconocido'}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <FormattedNumber
                                                        value={
                                                            consumption.quantity_gallons
                                                        }
                                                        maxDecimals={4}
                                                    />
                                                </td>
                                                <td className="p-3 text-right text-muted-foreground">
                                                    <FormattedNumber
                                                        value={
                                                            consumption.quantity_kg
                                                        }
                                                        maxDecimals={4}
                                                    />{' '}
                                                    kg
                                                </td>
                                                <td className="p-3 text-right">
                                                    {consumption.consumed_cost !=
                                                        null && (
                                                        <FormattedNumber
                                                            value={
                                                                consumption.consumed_cost
                                                            }
                                                            currency
                                                            maxDecimals={2}
                                                        />
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    {consumption.consumed_by
                                                        ?.name ?? '---'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                )}

                {canConsume && (
                    <div className="space-y-3 rounded-md border border-dashed border-emerald-300 bg-emerald-50/50 p-3 dark:border-emerald-800 dark:bg-emerald-950/10">
                        <p className="text-xs font-medium text-emerald-700 dark:text-emerald-400">
                            Consumir un saldo en esta orden
                        </p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                            <div className="space-y-1 sm:col-span-2">
                                <Combobox
                                    options={comboboxOptions}
                                    value={selectedRemnantId}
                                    onChange={(value) =>
                                        setSelectedRemnantId(value)
                                    }
                                    placeholder="Seleccionar saldo..."
                                    emptyText="No hay saldos disponibles"
                                />
                                {formErrors.remnant_id && (
                                    <p className="text-xs text-destructive">
                                        {formErrors.remnant_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <Input
                                    type="number"
                                    step="0.0001"
                                    min="0.0001"
                                    placeholder="Galones"
                                    className="h-9"
                                    value={quantityGallons}
                                    onChange={(event) =>
                                        setQuantityGallons(event.target.value)
                                    }
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') {
                                            e.preventDefault();
                                        }
                                    }}
                                />
                                {formErrors.quantity_gallons && (
                                    <p className="text-xs text-destructive">
                                        {formErrors.quantity_gallons}
                                    </p>
                                )}
                            </div>
                        </div>
                        {quantityGallons &&
                            activeRemnant?.density_kg_per_gallon && (
                                <p className="text-xs font-medium text-emerald-600 dark:text-emerald-400">
                                    ≈{' '}
                                    {(
                                        Number(quantityGallons) *
                                        Number(
                                            activeRemnant.density_kg_per_gallon,
                                        )
                                    ).toFixed(4)}{' '}
                                    kg
                                </p>
                            )}
                        <div className="space-y-1">
                            <Input
                                placeholder="Observaciones (opcional)"
                                className="h-9"
                                value={notes}
                                onChange={(event) =>
                                    setNotes(event.target.value)
                                }
                                onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                        e.preventDefault();
                                    }
                                }}
                            />
                        </div>
                        {formErrors.production_order && (
                            <p className="text-xs text-destructive">
                                {formErrors.production_order}
                            </p>
                        )}
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="border-emerald-300 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-700 dark:text-emerald-400 dark:hover:bg-emerald-950/30"
                            onClick={handleAdd}
                            disabled={
                                submitting || availableRemnants.length === 0
                            }
                        >
                            {submitting ? 'Consumiendo...' : 'Consumir Saldo'}
                        </Button>
                    </div>
                )}

                {consumedRemnants.length === 0 &&
                    !canConsume &&
                    !order.remnant && (
                        <p className="text-xs text-muted-foreground">
                            No hay saldos registrados para esta orden.
                        </p>
                    )}
            </CardContent>
        </Card>
    );
}
