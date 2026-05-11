import { Head, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Beaker,
    Clock,
    CheckCircle2,
    FileSpreadsheet,
    FileText,
    Plus,
    Trash2,
    User as UserIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    store as storeLineAdjustment,
    destroy as destroyLineAdjustment,
} from '@/actions/App/Http/Controllers/Production/LineAdjustmentController';
import {
    store as storePackagingPlan,
    destroy as destroyPackagingPlan,
} from '@/actions/App/Http/Controllers/Production/PackagingPlanController';
import {
    complete as productionOrderComplete,
    exportExcel as productionOrderExportExcel,
    exportPdf as productionOrderExportPdf,
    previewCosts as productionOrderPreviewCosts,
} from '@/actions/App/Http/Controllers/ProductionOrderController';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

type VariantOption = { id: number; sku: string; presentation_label: string; presentation_value: number };

type Props = {
    order: any;
    rawMaterials: Array<{ id: number; label: string }>;
    availableVariants: VariantOption[];
};

type PreviewCostData = {
    ingredients: Array<{ id: number; unit_cost: number; total_cost: number; actual_quantity: number }>;
    packaging: Array<{ id: number; cost_price: number; total_cost: number; equivalent: number; actual_units: number }>;
    total_bulk_cost: number;
    total_finished_cost: number;
    total_equivalent: number;
};

export default function ProductionOrderShow({ order, rawMaterials, availableVariants }: Props) {
    const isCompleted = order.status === 'completed';
    const hasOrderData = order.details.length > 0 || order.packaging_plans.length > 0;

    const { data, setData, post, processing, errors } = useForm({
        actual_yield_quantity: order.actual_quantity ?? order.quantity,
        viscosity_ku: order.viscosity_ku ?? '',
        grinding_hg: order.grinding_hg ?? '',
        agitation_start_time: order.agitation_start_time ?? '',
        agitation_end_time: order.agitation_end_time ?? '',
        packaging_start_time: order.packaging_start_time ?? '',
        packaging_end_time: order.packaging_end_time ?? '',
        responsible_name: order.responsible_name ?? '',
        spillage_quantity: order.spillage_quantity ?? 0,
        notes: order.notes ?? '',
        ingredients: order.details.map((detail: any) => ({
            id: detail.id,
            raw_material_name: detail.raw_material?.code,
            planned_quantity: detail.planned_quantity,
            actual_quantity: detail.actual_quantity ?? detail.planned_quantity,
            unit_cost: detail.unit_cost ?? 0,
            total_cost: detail.total_cost ?? 0,
        })),
        packaging: order.packaging_plans.map((pack: any) => ({
            id: pack.id,
            presentation: pack.product_variant?.presentation_label ?? 'Unidad',
            presentation_value: pack.product_variant?.presentation_value ?? 1,
            planned_units: pack.planned_units,
            actual_units: pack.actual_units ?? pack.planned_units,
            cost_price: pack.cost_price ?? null,
        })),
        line_adjustments: [], // Placeholder for validation errors
    });

    const [previewCosts, setPreviewCosts] = useState<PreviewCostData | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);

    // Re-sync packaging form data when plans are added/removed (after store/destroy redirects)
    const packagingPlanIds = useMemo(
        () => order.packaging_plans.map((p: any) => p.id).join(','),
        [order.packaging_plans]
    );

    useEffect(() => {
        setData('packaging', order.packaging_plans.map((pack: any) => {
            // Buscamos si el usuario ya había escrito algo para este plan en el formulario actual
            const existingFormItem = data.packaging.find((item: any) => item.id === pack.id);
            
            return {
                id: pack.id,
                presentation: pack.product_variant?.presentation_label ?? 'Unidad',
                presentation_value: pack.product_variant?.presentation_value ?? 1,
                planned_units: pack.planned_units,
                // Si existía en el form, conservamos lo que el usuario escribió; si no, usamos el valor del backend
                actual_units: existingFormItem ? existingFormItem.actual_units : (pack.actual_units ?? pack.planned_units),
                cost_price: pack.cost_price ?? null,
            };
        }));
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [packagingPlanIds]);

    useEffect(() => {
        if (isCompleted) {
            return;
        }

        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        const controller = new AbortController();
        let loadingIndicatorId: number | null = null;
        const timeoutId = window.setTimeout(async () => {
            loadingIndicatorId = window.setTimeout(() => {
                setPreviewLoading(true);
            }, 180);

            try {
                const response = await fetch(productionOrderPreviewCosts({ order: order.id }).url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        ingredients: data.ingredients.map((ingredient: any) => ({
                            id: ingredient.id,
                            actual_quantity: Number(ingredient.actual_quantity) || 0,
                        })),
                        packaging: data.packaging.map((pack: any) => ({
                            id: pack.id,
                            actual_units: Number(pack.actual_units) || 0,
                        })),
                    }),
                    signal: controller.signal,
                });

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as PreviewCostData;
                setPreviewCosts(payload);
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    // silently ignore preview errors to avoid blocking the UI
                }
            } finally {
                if (loadingIndicatorId !== null) {
                    window.clearTimeout(loadingIndicatorId);
                }

                setPreviewLoading(false);
            }
        }, 250);

        return () => {
            controller.abort();

            if (loadingIndicatorId !== null) {
                window.clearTimeout(loadingIndicatorId);
            }

            window.clearTimeout(timeoutId);
            setPreviewLoading(false);
        };
    }, [data.ingredients, data.packaging, isCompleted, order.id]);

    const previewIngredientsById = useMemo(() => {
        if (!previewCosts) {
            return new Map<number, PreviewCostData['ingredients'][number]>();
        }

        return new Map(previewCosts.ingredients.map((ingredient) => [ingredient.id, ingredient]));
    }, [previewCosts]);

    const previewPackagingById = useMemo(() => {
        if (!previewCosts) {
            return new Map<number, PreviewCostData['packaging'][number]>();
        }

        return new Map(previewCosts.packaging.map((pack) => [pack.id, pack]));
    }, [previewCosts]);

    const ingredientRows = isCompleted
        ? order.details.map((detail: any) => ({
              id: detail.id,
              raw_material_name: detail.raw_material?.code,
              planned_quantity: detail.planned_quantity,
              actual_quantity: detail.actual_quantity ?? detail.planned_quantity,
              unit_cost: detail.unit_cost ?? 0,
              total_cost: detail.total_cost ?? 0,
          }))
        : data.ingredients.map((ing: any) => ({
              ...ing,
              unit_cost: previewIngredientsById.get(ing.id)?.unit_cost ?? ing.unit_cost ?? 0,
              total_cost: previewIngredientsById.get(ing.id)?.total_cost ?? ing.total_cost ?? 0,
          }));

    const packagingRows = isCompleted
        ? order.packaging_plans.map((pack: any) => ({
              id: pack.id,
              presentation: pack.product_variant?.presentation_label ?? 'Unidad',
              presentation_value: pack.product_variant?.presentation_value ?? 1,
              planned_units: pack.planned_units,
              actual_units: pack.actual_units ?? pack.planned_units,
              cost_price: pack.cost_price ?? null,
          }))
        : data.packaging.map((pack: any) => ({
              ...pack,
              cost_price: previewPackagingById.get(pack.id)?.cost_price ?? pack.cost_price ?? 0,
          }));

    const totalEquivalent = isCompleted
        ? packagingRows.reduce((sum: number, pack: any) => {
              return sum + ((Number(pack.actual_units) || 0) * (Number(pack.presentation_value) || 0));
          }, 0)
        : (previewCosts?.total_equivalent ?? 0);

    const pendingBulkCost = previewCosts?.total_bulk_cost ?? 0;
    const pendingFinishedCost = previewCosts?.total_finished_cost ?? 0;
    const marginPercentage = Number(order.product?.profit_margin ?? 0);
    const activeFinishedCost = isCompleted ? Number(order.total_finished_cost || 0) : Number(pendingFinishedCost || 0);
    const estimatedMarginValue = activeFinishedCost * (marginPercentage / 100);
    const estimatedTargetValue = activeFinishedCost + estimatedMarginValue;

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (confirm('¿Estás seguro de finalizar esta orden? Esta acción actualizará los inventarios de forma irreversible.')) {
            post(productionOrderComplete({ order: order.id }).url, {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title={`Orden ${order.order_number}`} />
            <div className="p-6 space-y-6 max-w-7xl mx-auto">
                {/* Header */}
                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-3xl font-bold tracking-tight text-foreground">
                                Orden {order.order_number}
                            </h1>
                            <Badge variant={isCompleted ? 'default' : 'secondary'}>
                                {isCompleted ? 'Completada' : 'En Proceso'}
                            </Badge>
                        </div>
                        <p className="text-muted-foreground mt-1">
                            {order.product?.name} • Planta Cali • <FormattedNumber value={order.quantity} maxDecimals={2} /> gal Proyectados
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        {isCompleted && (
                            <div className="flex items-center gap-2 text-green-600 font-medium mr-4">
                                <CheckCircle2 className="w-5 h-5" />
                                Finalizada el {format(new Date(order.completion_date), 'dd/MM/yyyy HH:mm')}
                            </div>
                        )}
                        <a
                            href={productionOrderExportPdf.url(order.id)}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground transition-colors"
                        >
                            <FileText className="w-4 h-4" />
                            PDF
                        </a>
                        <a
                            href={productionOrderExportExcel.url(order.id)}
                            className="inline-flex items-center gap-1.5 rounded-md border border-input bg-background px-3 py-2 text-sm font-medium shadow-xs hover:bg-accent hover:text-accent-foreground transition-colors"
                        >
                            <FileSpreadsheet className="w-4 h-4" />
                            Excel
                        </a>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: Results & Quality */}
                    <div className="lg:col-span-2 space-y-6">
                        {/* Results Form */}
                        <Card className={isCompleted ? 'opacity-90 shadow-none' : ''}>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2">
                                    <Beaker className="w-5 h-5 text-primary" />
                                    Resultados de Fabricación
                                </CardTitle>
                                <CardDescription>
                                    Ingrese los datos reales obtenidos al finalizar el proceso.
                                    {!isCompleted ? ' Los costos se estiman en vivo desde servidor mientras editas.' : ''}
                                </CardDescription>
                                {!isCompleted && (
                                    <p className="h-5 text-xs text-muted-foreground">
                                        {previewLoading ? 'Recalculando costos...' : ''}
                                    </p>
                                )}
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div className="space-y-2">
                                        <Label htmlFor="actual-yield">Rendimiento Real (eq. gal)</Label>
                                        <Input
                                            id="actual-yield"
                                            type="number"
                                            step="0.0001"
                                            placeholder="Ej: 19.7500"
                                            value={data.actual_yield_quantity}
                                            onChange={e => setData('actual_yield_quantity', e.target.value)}
                                            disabled={isCompleted}
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Debe coincidir con el equivalente envasado dentro de la tolerancia.
                                        </p>
                                        {errors.actual_yield_quantity && (
                                            <p className="text-xs text-destructive">{errors.actual_yield_quantity}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="viscosity">Viscosidad (KU)</Label>
                                        <Input
                                            id="viscosity"
                                            type="number"
                                            step="0.01"
                                            placeholder="Ej: 105.5"
                                            value={data.viscosity_ku}
                                            onChange={e => setData('viscosity_ku', e.target.value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.viscosity_ku && <p className="text-xs text-destructive">{errors.viscosity_ku}</p>}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="grinding">Molienda (HG)</Label>
                                        <Input
                                            id="grinding"
                                            type="number"
                                            step="0.01"
                                            placeholder="Ej: 7.2"
                                            value={data.grinding_hg}
                                            onChange={e => setData('grinding_hg', e.target.value)}
                                            disabled={isCompleted}
                                        />
                                        {errors.grinding_hg && <p className="text-xs text-destructive">{errors.grinding_hg}</p>}
                                    </div>
                                </div>

                                <Separator />

                                <div className="space-y-4">
                                    <Label>Consumo Real de Insumos</Label>
                                    <div className="rounded-md border overflow-hidden">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50 border-b">
                                                <tr>
                                                    <th className="p-3 text-left">Materia Prima</th>
                                                    <th className="p-3 text-right">Planeado</th>
                                                    <th className="p-3 text-right w-32">Real Gastado</th>
                                                    <th className="p-3 text-right">Costo Unit.</th>
                                                    <th className="p-3 text-right">Costo Total</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {ingredientRows.map((ing: any, idx: number) => (
                                                    <tr key={ing.id} className="border-b last:border-0">
                                                        <td className="p-3 font-medium">{ing.raw_material_name}</td>
                                                        <td className="p-3 text-right text-muted-foreground"><FormattedNumber value={ing.planned_quantity} maxDecimals={2} /></td>
                                                        <td className="p-3">
                                                            <Input
                                                                className="h-8 text-right"
                                                                type="number"
                                                                step="0.0001"
                                                                value={ing.actual_quantity}
                                                                onChange={e => {
                                                                    const newIngs = [...data.ingredients];
                                                                    newIngs[idx].actual_quantity = e.target.value;
                                                                    setData('ingredients', newIngs);
                                                                }}
                                                                disabled={isCompleted}
                                                            />
                                                        </td>
                                                        <td className="p-3 text-right text-muted-foreground">
                                                            <FormattedNumber value={ing.unit_cost} currency maxDecimals={2} />
                                                        </td>
                                                        <td className="p-3 text-right font-medium">
                                                            <FormattedNumber value={ing.total_cost} currency maxDecimals={2} />
                                                        </td>
                                                    </tr>
                                                ))}
                                                {ingredientRows.length === 0 && (
                                                    <tr>
                                                        <td className="p-3 text-muted-foreground" colSpan={5}>
                                                            Esta orden no tiene insumos planificados.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                {/* Ajustes de Línea */}
                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Label className="flex items-center gap-1.5">
                                            <Plus className="w-4 h-4 text-orange-500" />
                                            Ajustes de Línea
                                        </Label>
                                        {!isCompleted && (
                                            <span className="text-xs text-muted-foreground">MPs adicionales fuera de fórmula</span>
                                        )}
                                    </div>

                                    {/* Tabla de ajustes existentes */}
                                    {(order.line_adjustments?.length > 0) && (
                                        <div className="rounded-md border overflow-hidden">
                                            <table className="w-full text-sm">
                                                <thead className="bg-orange-50 dark:bg-orange-950/20 border-b">
                                                    <tr>
                                                        <th className="p-3 text-left">Materia Prima</th>
                                                        <th className="p-3 text-right">Cantidad</th>
                                                        <th className="p-3 text-left">Motivo</th>
                                                        {!isCompleted && <th className="p-3 w-12"></th>}
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {order.line_adjustments.map((adj: any) => (
                                                        <tr key={adj.id} className="border-b last:border-0">
                                                            <td className="p-3 font-medium">{adj.raw_material?.code ?? 'N/A'}</td>
                                                            <td className="p-3 text-right"><FormattedNumber value={adj.quantity} maxDecimals={4} /></td>
                                                            <td className="p-3 text-muted-foreground">{adj.reason}</td>
                                                            {!isCompleted && (
                                                                <td className="p-3">
                                                                    <Button
                                                                        type="button"
                                                                        variant="ghost"
                                                                        size="icon"
                                                                        className="h-7 w-7 text-destructive hover:text-destructive"
                                                                        onClick={() => {
                                                                            if (confirm('¿Eliminar este ajuste de línea?')) {
                                                                                router.delete(destroyLineAdjustment({ order: order.id, adjustment: adj.id }).url, {
                                                                                    preserveScroll: true,
                                                                                });
                                                                            }
                                                                        }}
                                                                    >
                                                                        <Trash2 className="w-4 h-4" />
                                                                    </Button>
                                                                </td>
                                                            )}
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}

                                    {/* Formulario inline para agregar ajuste */}
                                    {!isCompleted && <LineAdjustmentForm orderId={order.id} rawMaterials={rawMaterials} />}

                                    {isCompleted && (order.line_adjustments?.length ?? 0) === 0 && (
                                        <p className="text-xs text-muted-foreground">No se registraron ajustes de línea en esta orden.</p>
                                    )}
                                </div>

                                <div className="space-y-4">
                                    <div className="flex items-center justify-between">
                                        <Label>Empaque Final (Unidades)</Label>
                                        {!isCompleted && (
                                            <span className="text-xs text-muted-foreground">Puedes agregar o eliminar presentaciones</span>
                                        )}
                                    </div>
                                    <div className="rounded-md border overflow-hidden">
                                        <table className="w-full text-sm">
                                            <thead className="bg-muted/50 border-b">
                                                <tr>
                                                    <th className="p-3 text-left">Presentación</th>
                                                    <th className="p-3 text-right">Planeado</th>
                                                    <th className="p-3 text-right w-32">Real Producido</th>
                                                    <th className="p-3 text-right">Eq. Gal</th>
                                                    <th className="p-3 text-right">Costo Unit.</th>
                                                    <th className="p-3 text-right">Costo Total</th>
                                                    {!isCompleted && <th className="p-3 w-12"></th>}
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {packagingRows.map((pack: any, idx: number) => (
                                                    <tr key={pack.id} className="border-b last:border-0">
                                                        <td className="p-3 font-medium">{pack.presentation}</td>
                                                        <td className="p-3 text-right text-muted-foreground"><FormattedNumber value={pack.planned_units} maxDecimals={0} /></td>
                                                        <td className="p-3">
                                                            <Input
                                                                className="h-8 text-right"
                                                                type="number"
                                                                step="1"
                                                                value={pack.actual_units}
                                                                onChange={e => {
                                                                    const newPack = [...data.packaging];
                                                                    newPack[idx].actual_units = e.target.value;
                                                                    setData('packaging', newPack);
                                                                }}
                                                                disabled={isCompleted}
                                                            />
                                                        </td>
                                                        <td className="p-3 text-right text-muted-foreground">
                                                            <FormattedNumber value={(Number(pack.actual_units) || 0) * (Number(pack.presentation_value) || 0)} maxDecimals={2} />
                                                        </td>
                                                        <td className="p-3 text-right text-muted-foreground">
                                                            <FormattedNumber value={pack.cost_price} currency maxDecimals={2} />
                                                        </td>
                                                        <td className="p-3 text-right font-medium">
                                                            <FormattedNumber value={(Number(pack.actual_units) || 0) * (Number(pack.cost_price) || 0)} currency maxDecimals={2} />
                                                        </td>
                                                        {!isCompleted && (
                                                            <td className="p-3">
                                                                <Button
                                                                    type="button"
                                                                    variant="ghost"
                                                                    size="icon"
                                                                    className="h-7 w-7 text-destructive hover:text-destructive"
                                                                    onClick={() => {
                                                                        if (confirm('¿Eliminar esta presentación del plan de envasado?')) {
                                                                            router.delete(destroyPackagingPlan({ order: order.id, plan: pack.id }).url, {
                                                                                preserveScroll: true,
                                                                            });
                                                                        }
                                                                    }}
                                                                >
                                                                    <Trash2 className="w-4 h-4" />
                                                                </Button>
                                                            </td>
                                                        )}
                                                    </tr>
                                                ))}
                                                {packagingRows.length === 0 && (
                                                    <tr>
                                                        <td className="p-3 text-muted-foreground" colSpan={isCompleted ? 6 : 7}>
                                                            Esta orden no tiene plan de empaque. {!isCompleted && 'Agrega presentaciones abajo.'}
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>

                                    {/* Formulario inline para agregar presentación */}
                                    {!isCompleted && <PackagingPlanForm orderId={order.id} availableVariants={availableVariants} />}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Times & Responsible */}
                    <div className="space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="flex items-center gap-2 text-base">
                                    <Clock className="w-4 h-4 text-primary" />
                                    Tiempos y Control
                                </CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="responsible" className="flex items-center gap-1">
                                        <UserIcon className="w-3 h-3" /> Responsable
                                    </Label>
                                    <Input
                                        id="responsible"
                                        placeholder="Nombre del operario"
                                        value={data.responsible_name}
                                        onChange={e => setData('responsible_name', e.target.value)}
                                        disabled={isCompleted}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="spillage">Derrame Detectado (gal)</Label>
                                    <Input
                                        id="spillage"
                                        type="number"
                                        step="0.01"
                                        value={data.spillage_quantity}
                                        onChange={e => setData('spillage_quantity', e.target.value)}
                                        disabled={isCompleted}
                                    />
                                </div>
                                <Separator />
                                <div className="grid grid-cols-1 gap-4">
                                    <div className="space-y-2">
                                        <Label className="text-xs">Inicio Agitación</Label>
                                        <Input
                                            type="datetime-local"
                                            className="h-9 text-xs"
                                            value={data.agitation_start_time}
                                            onChange={e => setData('agitation_start_time', e.target.value)}
                                            disabled={isCompleted}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label className="text-xs">Fin Empaque</Label>
                                        <Input
                                            type="datetime-local"
                                            className="h-9 text-xs"
                                            value={data.packaging_end_time}
                                            onChange={e => setData('packaging_end_time', e.target.value)}
                                            disabled={isCompleted}
                                        />
                                    </div>
                                </div>
                                <Separator />
                                <div className="space-y-2">
                                    <Label htmlFor="notes">Observaciones</Label>
                                    <Textarea
                                        id="notes"
                                        placeholder="Notas sobre el lote..."
                                        value={data.notes}
                                        onChange={e => setData('notes', e.target.value)}
                                        disabled={isCompleted}
                                    />
                                </div>

                                {!isCompleted && (
                                    <>
                                        {errors.packaging && (
                                            <p className="text-xs text-destructive">{errors.packaging}</p>
                                        )}
                                        {errors.ingredients && (
                                            <p className="text-xs text-destructive">{errors.ingredients}</p>
                                        )}
                                        {errors.line_adjustments && (
                                            <p className="text-xs text-destructive">{errors.line_adjustments}</p>
                                        )}
                                    </>
                                )}

                                {!isCompleted && (
                                    <Button 
                                        type="submit" 
                                        className="w-full mt-4" 
                                        size="lg"
                                        disabled={processing || !hasOrderData}
                                    >
                                        {processing ? 'Finalizando...' : 'Finalizar Producción'}
                                    </Button>
                                )}
                                {!isCompleted && !hasOrderData && (
                                    <p className="text-xs text-destructive">
                                        La orden no tiene detalle de insumos ni plan de empaque. Revise la fórmula y vuelva a crearla.
                                    </p>
                                )}
                            </CardContent>
                        </Card>

                        {/* Order Info */}
                        <Card className="bg-muted/40">
                            <CardContent className="p-4 space-y-2 text-xs">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Fórmula:</span>
                                    <span className="font-medium">v{order.formula?.version}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Fecha Plan:</span>
                                    <span className="font-medium">{order.planned_date}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Bodega:</span>
                                    <span className="font-medium">{order.warehouse?.name}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Rend. envasado (eq. gal):</span>
                                    <span className="font-medium"><FormattedNumber value={totalEquivalent} maxDecimals={2} /></span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Costo granel acumulado:</span>
                                    <span className="font-medium"><FormattedNumber value={isCompleted ? order.total_bulk_cost : pendingBulkCost} currency maxDecimals={2} /></span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Costo terminado total:</span>
                                    <span className="font-medium"><FormattedNumber value={isCompleted ? order.total_finished_cost : pendingFinishedCost} currency maxDecimals={2} /></span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Margen producto (%):</span>
                                    <span className="font-medium"><FormattedNumber value={marginPercentage} maxDecimals={2} />%</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Margen estimado:</span>
                                    <span className="font-medium"><FormattedNumber value={estimatedMarginValue} currency maxDecimals={2} /></span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">Valor objetivo c/margen:</span>
                                    <span className="font-medium"><FormattedNumber value={estimatedTargetValue} currency maxDecimals={2} /></span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}

function LineAdjustmentForm({ orderId, rawMaterials }: { orderId: number; rawMaterials: Array<{ id: number; label: string }> }) {
    const [rawMaterialId, setRawMaterialId] = useState<number | null>(null);
    const [quantity, setQuantity] = useState('');
    const [reason, setReason] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const comboboxOptions = rawMaterials.map((rm) => ({ id: rm.id, label: rm.label }));

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

        router.post(storeLineAdjustment({ order: orderId }).url, {
            raw_material_id: rawMaterialId,
            quantity: Number(quantity),
            reason: reason.trim(),
        }, {
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
        });
    };

    return (
        <div className="rounded-md border border-dashed border-orange-300 dark:border-orange-800 bg-orange-50/50 dark:bg-orange-950/10 p-3 space-y-3">
            <p className="text-xs font-medium text-orange-700 dark:text-orange-400">Agregar ajuste de línea</p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div className="space-y-1">
                    <Combobox
                        options={comboboxOptions}
                        value={rawMaterialId}
                        onChange={(v) => setRawMaterialId(Number(v))}
                        placeholder="Materia prima..."
                        emptyText="Sin resultados"
                    />
                    {formErrors.raw_material_id && <p className="text-xs text-destructive">{formErrors.raw_material_id}</p>}
                </div>
                <div className="space-y-1">
                    <Input
                        type="number"
                        step="0.0001"
                        min="0.0001"
                        placeholder="Cantidad"
                        className="h-9"
                        value={quantity}
                        onChange={(e) => setQuantity(e.target.value)}
                    />
                    {formErrors.quantity && <p className="text-xs text-destructive">{formErrors.quantity}</p>}
                </div>
                <div className="space-y-1">
                    <Input
                        placeholder="Motivo (ej: corrección viscosidad)"
                        className="h-9"
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        maxLength={500}
                    />
                    {formErrors.reason && <p className="text-xs text-destructive">{formErrors.reason}</p>}
                </div>
            </div>
            {formErrors.production_order && <p className="text-xs text-destructive">{formErrors.production_order}</p>}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="border-orange-300 dark:border-orange-700 text-orange-700 dark:text-orange-400 hover:bg-orange-100 dark:hover:bg-orange-950/30"
                onClick={handleAdd}
                disabled={submitting}
            >
                <Plus className="w-4 h-4 mr-1" />
                {submitting ? 'Guardando...' : 'Agregar'}
            </Button>
        </div>
    );
}

function PackagingPlanForm({ orderId, availableVariants }: { orderId: number; availableVariants: VariantOption[] }) {
    const [variantId, setVariantId] = useState<number | null>(null);
    const [plannedUnits, setPlannedUnits] = useState('');
    const [submitting, setSubmitting] = useState(false);
    const [formErrors, setFormErrors] = useState<Record<string, string>>({});

    const comboboxOptions = availableVariants.map((v) => ({
        id: v.id,
        label: `${v.sku} — ${v.presentation_label} (${v.presentation_value} gal)`,
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

        router.post(storePackagingPlan({ order: orderId }).url, {
            product_variant_id: variantId,
            planned_units: Number(plannedUnits),
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setVariantId(null);
                setPlannedUnits('');
            },
            onError: (errors) => {
                setFormErrors(errors as Record<string, string>);
            },
            onFinish: () => setSubmitting(false),
        });
    };

    return (
        <div className="rounded-md border border-dashed border-blue-300 dark:border-blue-800 bg-blue-50/50 dark:bg-blue-950/10 p-3 space-y-3">
            <p className="text-xs font-medium text-blue-700 dark:text-blue-400">Agregar presentación al plan de envasado</p>
            <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                <div className="sm:col-span-2 space-y-1">
                    <Combobox
                        options={comboboxOptions}
                        value={variantId}
                        onChange={(v) => setVariantId(Number(v))}
                        placeholder="Presentación..."
                        emptyText="Sin resultados"
                    />
                    {formErrors.product_variant_id && <p className="text-xs text-destructive">{formErrors.product_variant_id}</p>}
                </div>
                <div className="space-y-1">
                    <Input
                        type="number"
                        step="1"
                        min="1"
                        placeholder="Unidades planeadas"
                        className="h-9"
                        value={plannedUnits}
                        onChange={(e) => setPlannedUnits(e.target.value)}
                    />
                    {formErrors.planned_units && <p className="text-xs text-destructive">{formErrors.planned_units}</p>}
                </div>
            </div>
            {formErrors.production_order && <p className="text-xs text-destructive">{formErrors.production_order}</p>}
            <Button
                type="button"
                variant="outline"
                size="sm"
                className="border-blue-300 dark:border-blue-700 text-blue-700 dark:text-blue-400 hover:bg-blue-100 dark:hover:bg-blue-950/30"
                onClick={handleAdd}
                disabled={submitting}
            >
                <Plus className="w-4 h-4 mr-1" />
                {submitting ? 'Guardando...' : 'Agregar Presentación'}
            </Button>
        </div>
    );
}
