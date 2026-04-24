import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Beaker,
    Clock,
    CheckCircle2,
    FileSpreadsheet,
    FileText,
    User as UserIcon,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

type Props = {
    order: any; // Simplified for initial build, will refine types as we go
};

type PreviewCostData = {
    ingredients: Array<{ id: number; unit_cost: number; total_cost: number; actual_quantity: number }>;
    packaging: Array<{ id: number; cost_price: number; total_cost: number; equivalent: number; actual_units: number }>;
    total_bulk_cost: number;
    total_finished_cost: number;
    total_equivalent: number;
};

export default function ProductionOrderShow({ order }: Props) {
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
    });

    const [previewCosts, setPreviewCosts] = useState<PreviewCostData | null>(null);
    const [previewLoading, setPreviewLoading] = useState(false);

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
                preserveState: false,
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

                                <div className="space-y-4">
                                    <Label>Empaque Final (Unidades)</Label>
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
                                                    </tr>
                                                ))}
                                                {packagingRows.length === 0 && (
                                                    <tr>
                                                        <td className="p-3 text-muted-foreground" colSpan={6}>
                                                            Esta orden no tiene plan de empaque.
                                                        </td>
                                                    </tr>
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
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
