import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import {
    destroy as destroyPackagingPlan,
    store as storePackagingPlan,
} from '@/actions/App/Http/Controllers/Production/PackagingPlanController';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    ProductionOrderFormData,
    ProductionOrderPackagingFormRow,
    ProductionOrderSetData,
    VariantOption,
} from '@/types/production-orders';

type PackagingSectionProps = {
    orderId: number;
    rows: ProductionOrderPackagingFormRow[];
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    availableVariants: VariantOption[];
    isCompleted: boolean;
};

export function PackagingSection({
    orderId,
    rows,
    data,
    setData,
    availableVariants,
    isCompleted,
}: PackagingSectionProps) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <Label>Empaque Final (Unidades)</Label>
                {!isCompleted && (
                    <span className="text-xs text-muted-foreground">
                        Puedes agregar o eliminar presentaciones
                    </span>
                )}
            </div>
            <div className="overflow-hidden rounded-md border">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="border-b bg-muted/50">
                            <tr>
                                <th className="p-3 text-left">Presentación</th>
                                <th className="p-3 text-right">Planeado</th>
                                <th className="w-32 p-3 text-right">
                                    Real Producido
                                </th>
                                <th className="p-3 text-right">Eq. Gal</th>
                                <th className="p-3 text-right">Costo Unit.</th>
                                <th className="p-3 text-right">Costo Total</th>
                                {!isCompleted && <th className="w-12 p-3"></th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((pack, index) => (
                                <tr
                                    key={pack.id}
                                    className="border-b last:border-0"
                                >
                                    <td className="p-3 font-medium">
                                        {pack.presentation}
                                    </td>
                                    <td className="p-3 text-right text-muted-foreground">
                                        <FormattedNumber
                                            value={pack.planned_units}
                                            maxDecimals={0}
                                        />
                                    </td>
                                    <td className="p-3">
                                        <Input
                                            className="h-8 text-right"
                                            type="number"
                                            step="1"
                                            value={pack.actual_units}
                                            onChange={(event) => {
                                                const newPackaging = [
                                                    ...data.packaging,
                                                ];
                                                newPackaging[index] = {
                                                    ...newPackaging[index],
                                                    actual_units:
                                                        event.target.value,
                                                };
                                                setData(
                                                    'packaging',
                                                    newPackaging,
                                                );
                                            }}
                                            disabled={isCompleted}
                                        />
                                    </td>
                                    <td className="p-3 text-right text-muted-foreground">
                                        <FormattedNumber
                                            value={
                                                (Number(pack.actual_units) ||
                                                    0) *
                                                (Number(
                                                    pack.presentation_value,
                                                ) || 0)
                                            }
                                            maxDecimals={2}
                                        />
                                    </td>
                                    <td className="p-3 text-right text-muted-foreground">
                                        <FormattedNumber
                                            value={pack.cost_price}
                                            currency
                                            maxDecimals={2}
                                        />
                                    </td>
                                    <td className="p-3 text-right font-medium">
                                        <FormattedNumber
                                            value={
                                                (Number(pack.actual_units) ||
                                                    0) *
                                                (Number(pack.cost_price) || 0)
                                            }
                                            currency
                                            maxDecimals={2}
                                        />
                                    </td>
                                    {!isCompleted && (
                                        <td className="p-3">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7 text-destructive hover:text-destructive"
                                                onClick={() => {
                                                    if (
                                                        confirm(
                                                            '¿Eliminar esta presentación del plan de envasado?',
                                                        )
                                                    ) {
                                                        router.delete(
destroyPackagingPlan(
    {
        production_order: orderId,
        plan: pack.id,
                                                                },
                                                            ).url,
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    }
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        className="p-3 text-muted-foreground"
                                        colSpan={isCompleted ? 6 : 7}
                                    >
                                        Esta orden no tiene plan de empaque.{' '}
                                        {!isCompleted &&
                                            'Agrega presentaciones abajo.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {!isCompleted && (
                <PackagingPlanForm
                    orderId={orderId}
                    availableVariants={availableVariants}
                />
            )}
        </div>
    );
}

function PackagingPlanForm({
    orderId,
    availableVariants,
}: {
    orderId: number;
    availableVariants: VariantOption[];
}) {
    const [variantId, setVariantId] = useState<number | null>(null);
    const [plannedUnits, setPlannedUnits] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const comboboxOptions = availableVariants.map((variant) => ({
        id: variant.id,
        label: `${variant.sku} — ${variant.presentation_label} (${variant.presentation_value} gal)`,
    }));

    const handleAdd = () => {
        if (!variantId || !plannedUnits) {
            const errors: Record<string, string> = {};

            if (!variantId) {
                errors.product_variant_id = 'Seleccione una presentación.';
            }

            if (!plannedUnits) {
                errors.planned_units = 'Ingrese unidades.';
            }

            setFormErrors(errors);

            return;
        }

        setSubmitting(true);
        setFormErrors({});

        router.post(
            storePackagingPlan({ production_order: orderId }).url,
            {
                product_variant_id: variantId,
                planned_units: Number(plannedUnits),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setVariantId(null);
                    setPlannedUnits('');
                },
                onError: (errors) => {
                    setFormErrors(errors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <div className="space-y-3 rounded-md border border-dashed border-blue-300 bg-blue-50/50 p-3 dark:border-blue-800 dark:bg-blue-950/10">
            <p className="text-xs font-medium text-blue-700 dark:text-blue-400">
                Agregar presentación al plan de envasado
            </p>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div className="space-y-1 sm:col-span-2">
                    <Combobox
                        options={comboboxOptions}
                        value={variantId}
                        onChange={(value) => setVariantId(Number(value))}
                        placeholder="Presentación..."
                        emptyText="Sin resultados"
                    />
                    {formErrors.product_variant_id && (
                        <p className="text-xs text-destructive">
                            {formErrors.product_variant_id}
                        </p>
                    )}
                </div>
                <div className="space-y-1">
                    <Input
                        type="number"
                        step="1"
                        min="1"
                        placeholder="Unidades planeadas"
                        className="h-9"
                        value={plannedUnits}
                        onChange={(event) =>
                            setPlannedUnits(event.target.value)
                        }
                    />
                    {formErrors.planned_units && (
                        <p className="text-xs text-destructive">
                            {formErrors.planned_units}
                        </p>
                    )}
                </div>
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
                className="border-blue-300 text-blue-700 hover:bg-blue-100 dark:border-blue-700 dark:text-blue-400 dark:hover:bg-blue-950/30"
                onClick={handleAdd}
                disabled={submitting}
            >
                <Plus className="mr-1 h-4 w-4" />
                {submitting ? 'Guardando...' : 'Agregar Presentación'}
            </Button>
        </div>
    );
}
