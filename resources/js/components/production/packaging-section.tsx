import { router } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { Fragment, useState } from 'react';

import {
    destroy as destroyPackagingPlan,
    store as storePackagingPlan,
} from '@/actions/App/Http/Controllers/Production/PackagingPlanController';
import { FormattedNumber } from '@/components/formatted-number';
import { PrintLabelsButton } from '@/components/production/print-labels-button';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type {
    LabelMaterialOption,
    ProductionOrderFormData,
    ProductionOrderPackagingFormRow,
    ProductionOrderSetData,
    VariantOption,
} from '@/types/production-orders';

/** Valor del selector para «sin etiqueta»: un SelectItem no admite valor vacío. */
const NO_LABEL = 'none';

type PackagingSectionProps = {
    orderId: number;
    rows: ProductionOrderPackagingFormRow[];
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    availableVariants: VariantOption[];
    labelMaterials: LabelMaterialOption[];
    isReadOnly: boolean;
    showCosts?: boolean;
    /** Estampitas de lote: también con la orden en solo lectura (reimprimir una completada). */
    canPrintLabels?: boolean;
};

export function PackagingSection({
    orderId,
    rows,
    data,
    setData,
    availableVariants,
    labelMaterials,
    isReadOnly,
    showCosts = true,
    canPrintLabels = false,
}: PackagingSectionProps) {
    const showActions = !isReadOnly || canPrintLabels;
    const columnCount = (showCosts ? 6 : 4) + (showActions ? 1 : 0);

    const updateRow = (
        index: number,
        patch: Partial<ProductionOrderPackagingFormRow>,
    ) => {
        const newPackaging = [...data.packaging];
        newPackaging[index] = { ...newPackaging[index], ...patch };
        setData('packaging', newPackaging);
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between">
                <Label>Empaque Final (Unidades)</Label>
                {!isReadOnly && (
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
                                {showCosts && (
                                    <>
                                        <th className="p-3 text-right">
                                            Costo Unit.
                                        </th>
                                        <th className="p-3 text-right">
                                            Costo Total
                                        </th>
                                    </>
                                )}
                                {showActions && <th className="w-20 p-3"></th>}
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((pack, index) => (
                                <Fragment key={pack.id}>
                                    <tr>
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
                                                onChange={(event) =>
                                                    updateRow(index, {
                                                        actual_units:
                                                            event.target.value,
                                                    })
                                                }
                                                disabled={isReadOnly}
                                            />
                                        </td>
                                        <td className="p-3 text-right text-muted-foreground">
                                            <FormattedNumber
                                                value={
                                                    (Number(
                                                        pack.actual_units,
                                                    ) || 0) *
                                                    (Number(
                                                        pack.presentation_value,
                                                    ) || 0)
                                                }
                                                maxDecimals={2}
                                            />
                                        </td>
                                        {showCosts && (
                                            <>
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
                                                            (Number(
                                                                pack.actual_units,
                                                            ) || 0) *
                                                            (Number(
                                                                pack.cost_price,
                                                            ) || 0)
                                                        }
                                                        currency
                                                        maxDecimals={2}
                                                    />
                                                </td>
                                            </>
                                        )}
                                        {showActions && (
                                            <td className="p-3">
                                                <div className="flex items-center justify-end gap-2">
                                                    {canPrintLabels && (
                                                        <PrintLabelsButton
                                                            orderId={orderId}
                                                            planId={pack.id}
                                                        />
                                                    )}
                                                    {!isReadOnly && (
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
                                                                                production_order:
                                                                                    orderId,
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
                                                    )}
                                                </div>
                                            </td>
                                        )}
                                    </tr>
                                    <PackagingMaterialsRow
                                        pack={pack}
                                        columnCount={columnCount}
                                        labelMaterials={labelMaterials}
                                        isReadOnly={isReadOnly}
                                        onChange={(patch) =>
                                            updateRow(index, patch)
                                        }
                                    />
                                </Fragment>
                            ))}
                            {rows.length === 0 && (
                                <tr>
                                    <td
                                        className="p-3 text-muted-foreground"
                                        colSpan={columnCount}
                                    >
                                        Esta orden no tiene plan de empaque.{' '}
                                        {!isReadOnly &&
                                            'Agrega presentaciones abajo.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            {/* Una presentación va una sola vez por orden: sin presentaciones por agregar, no hay formulario. */}
            {!isReadOnly &&
                (availableVariants.length > 0 ? (
                    <PackagingPlanForm
                        orderId={orderId}
                        availableVariants={availableVariants}
                    />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No quedan presentaciones activas por agregar a esta
                        orden.
                    </p>
                ))}
        </div>
    );
}

/**
 * Envase y etiqueta de una presentación (3.7). Vacío = tantos como unidades envasadas; los envases reutilizados no se
 * descuentan ni cuestan. Sin tope: la merma (envase dañado, etiqueta mal pegada) es costo del lote.
 */
function PackagingMaterialsRow({
    pack,
    columnCount,
    labelMaterials,
    isReadOnly,
    onChange,
}: {
    pack: ProductionOrderPackagingFormRow;
    columnCount: number;
    labelMaterials: LabelMaterialOption[];
    isReadOnly: boolean;
    onChange: (patch: Partial<ProductionOrderPackagingFormRow>) => void;
}) {
    // Una etiqueta inactiva solo se ofrece al plan que la tiene guardada, aunque el operario elija otra y quiera volver.
    const labelOptions = labelMaterials.filter(
        (label) =>
            label.is_active ||
            label.id === pack.saved_label_raw_material_id ||
            label.id === pack.label_raw_material_id,
    );
    const unitsPlaceholder = `= ${pack.actual_units || 0} (unidades)`;

    return (
        <tr className="border-b last:border-0">
            <td colSpan={columnCount} className="px-3 pt-0 pb-3">
                <div className="grid gap-3 rounded-md bg-muted/30 p-3 text-xs sm:grid-cols-2 lg:grid-cols-4">
                    <div className="space-y-1">
                        <span className="text-muted-foreground">Envase</span>
                        <p className="font-mono text-sm">
                            {pack.package_code ?? 'Sin envase'}
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`new-containers-${pack.id}`}>
                            Envases nuevos usados
                        </Label>
                        <Input
                            id={`new-containers-${pack.id}`}
                            className="h-8 text-right"
                            type="number"
                            step="1"
                            min="0"
                            value={pack.new_containers_used}
                            placeholder={unitsPlaceholder}
                            onChange={(event) =>
                                onChange({
                                    new_containers_used: event.target.value,
                                })
                            }
                            disabled={isReadOnly || !pack.package_code}
                        />
                        <p className="text-muted-foreground">
                            Los reutilizados no se descuentan ni cuestan.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`label-${pack.id}`}>Etiqueta</Label>
                        <Select
                            value={
                                pack.label_raw_material_id != null
                                    ? String(pack.label_raw_material_id)
                                    : NO_LABEL
                            }
                            onValueChange={(value) =>
                                // Sin etiqueta tampoco hay etiquetas usadas: el campo se deshabilita y se vacía.
                                onChange(
                                    value === NO_LABEL
                                        ? {
                                              label_raw_material_id: null,
                                              labels_used: '',
                                          }
                                        : {
                                              label_raw_material_id:
                                                  Number(value),
                                          },
                                )
                            }
                            disabled={isReadOnly}
                        >
                            <SelectTrigger
                                id={`label-${pack.id}`}
                                className="h-8"
                            >
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value={NO_LABEL}>
                                    Sin etiqueta
                                </SelectItem>
                                {labelOptions.map((label) => (
                                    <SelectItem
                                        key={label.id}
                                        value={String(label.id)}
                                    >
                                        {label.is_active
                                            ? label.code
                                            : `${label.code} (inactiva)`}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1">
                        <Label htmlFor={`labels-used-${pack.id}`}>
                            Etiquetas usadas
                        </Label>
                        <Input
                            id={`labels-used-${pack.id}`}
                            className="h-8 text-right"
                            type="number"
                            step="1"
                            min="0"
                            value={pack.labels_used}
                            placeholder={unitsPlaceholder}
                            onChange={(event) =>
                                onChange({ labels_used: event.target.value })
                            }
                            disabled={
                                isReadOnly || pack.label_raw_material_id == null
                            }
                        />
                    </div>
                </div>
            </td>
        </tr>
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
        label: `${variant.name} — ${variant.presentation_label} (${variant.presentation_value} gal)`,
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
