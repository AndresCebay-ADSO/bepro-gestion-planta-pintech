import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

import {
    destroy as destroyLineAdjustment,
    store as storeLineAdjustment,
} from '@/actions/App/Http/Controllers/Production/LineAdjustmentController';
import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type {
    ProductionOrderLineAdjustment,
    RawMaterialOption,
} from '@/types/production-orders';

type LineAdjustmentsPanelProps = {
    orderId: number;
    adjustments: ProductionOrderLineAdjustment[];
    rawMaterials: RawMaterialOption[];
    isCompleted: boolean;
};

export function LineAdjustmentsPanel({
    orderId,
    adjustments,
    rawMaterials,
    isCompleted,
}: LineAdjustmentsPanelProps) {
    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <Label className="flex items-center gap-1.5">
                    <Plus className="h-4 w-4 text-orange-500" />
                    Ajustes de Línea
                </Label>
                {!isCompleted && (
                    <span className="text-xs text-muted-foreground">
                        MPs adicionales fuera de fórmula
                    </span>
                )}
            </div>

            {adjustments.length > 0 && (
                <div className="overflow-hidden rounded-md border">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-orange-50 dark:bg-orange-950/20">
                                <tr>
                                    <th className="p-3 text-left">
                                        Materia Prima
                                    </th>
                                    <th className="p-3 text-right">Cantidad</th>
                                    <th className="p-3 text-left">Motivo</th>
                                    {!isCompleted && (
                                        <th className="w-12 p-3"></th>
                                    )}
                                </tr>
                            </thead>
                            <tbody>
                                {adjustments.map((adjustment) => (
                                    <tr
                                        key={adjustment.id}
                                        className="border-b last:border-0"
                                    >
                                        <td className="p-3 font-medium">
                                            {adjustment.raw_material?.code ??
                                                'N/A'}
                                        </td>
                                        <td className="p-3 text-right">
                                            <FormattedNumber
                                                value={adjustment.quantity}
                                                maxDecimals={4}
                                            />
                                        </td>
                                        <td className="p-3 text-muted-foreground">
                                            {adjustment.reason}
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
                                                                '¿Eliminar este ajuste de línea?',
                                                            )
                                                        ) {
                                                            router.delete(
                                                                destroyLineAdjustment(
                                                                    {
                                                                        order: orderId,
                                                                        adjustment:
                                                                            adjustment.id,
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
                            </tbody>
                        </table>
                    </div>
                </div>
            )}

            {!isCompleted && (
                <LineAdjustmentForm
                    orderId={orderId}
                    rawMaterials={rawMaterials}
                />
            )}

            {isCompleted && adjustments.length === 0 && (
                <p className="text-xs text-muted-foreground">
                    No se registraron ajustes de línea en esta orden.
                </p>
            )}
        </div>
    );
}

function LineAdjustmentForm({
    orderId,
    rawMaterials,
}: {
    orderId: number;
    rawMaterials: RawMaterialOption[];
}) {
    const [rawMaterialId, setRawMaterialId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const comboboxOptions = rawMaterials.map((rawMaterial) => ({
        id: rawMaterial.id,
        label: rawMaterial.label,
    }));

    const handleAdd = () => {
        if (!rawMaterialId || !quantity || !reason.trim()) {
            const errors: Record<string, string> = {};

            if (!rawMaterialId) {
                errors.raw_material_id = 'Seleccione una MP.';
            }

            if (!quantity) {
                errors.quantity = 'Ingrese cantidad.';
            }

            if (!reason.trim()) {
                errors.reason = 'Ingrese motivo.';
            }

            setFormErrors(errors);

            return;
        }

        setSubmitting(true);
        setFormErrors({});

        router.post(
            storeLineAdjustment({ order: orderId }).url,
            {
                raw_material_id: rawMaterialId,
                quantity: Number(quantity),
                reason: reason.trim(),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setRawMaterialId(null);
                    setQuantity('');
                    setReason('');
                },
                onError: (errors) => {
                    setFormErrors(errors as Record<string, string>);
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <div className="space-y-3 rounded-md border border-dashed border-orange-300 bg-orange-50/50 p-3 dark:border-orange-800 dark:bg-orange-950/10">
            <p className="text-xs font-medium text-orange-700 dark:text-orange-400">
                Agregar ajuste de línea
            </p>
            <div className="grid grid-cols-1 gap-2 sm:grid-cols-3">
                <div className="space-y-1">
                    <Combobox
                        options={comboboxOptions}
                        value={rawMaterialId}
                        onChange={(value) => setRawMaterialId(Number(value))}
                        placeholder="Materia prima..."
                        emptyText="Sin resultados"
                    />
                    {formErrors.raw_material_id && (
                        <p className="text-xs text-destructive">
                            {formErrors.raw_material_id}
                        </p>
                    )}
                </div>
                <div className="space-y-1">
                    <Input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        placeholder="Cantidad"
                        className="h-9"
                        value={quantity}
                        onChange={(event) => setQuantity(event.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                            }
                        }}
                    />
                    {formErrors.quantity && (
                        <p className="text-xs text-destructive">
                            {formErrors.quantity}
                        </p>
                    )}
                </div>
                <div className="space-y-1">
                    <Input
                        placeholder="Motivo (ej: corrección viscosidad)"
                        className="h-9"
                        value={reason}
                        onChange={(event) => setReason(event.target.value)}
                        maxLength={500}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.preventDefault();
                            }
                        }}
                    />
                    {formErrors.reason && (
                        <p className="text-xs text-destructive">
                            {formErrors.reason}
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
                className="border-orange-300 text-orange-700 hover:bg-orange-100 dark:border-orange-700 dark:text-orange-400 dark:hover:bg-orange-950/30"
                onClick={handleAdd}
                disabled={submitting}
            >
                <Plus className="mr-1 h-4 w-4" />
                {submitting ? 'Guardando...' : 'Agregar'}
            </Button>
        </div>
    );
}
