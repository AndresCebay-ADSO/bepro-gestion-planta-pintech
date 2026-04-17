import { Head, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    Beaker,
    Clock,
    CheckCircle2,
    User as UserIcon,
} from 'lucide-react';
import { complete as productionOrderComplete } from '@/actions/App/Http/Controllers/ProductionOrderController';
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

export default function ProductionOrderShow({ order }: Props) {
    const isCompleted = order.status === 'completed';

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
            raw_material_name: detail.raw_material?.name,
            planned_quantity: detail.planned_quantity,
            actual_quantity: detail.actual_quantity ?? detail.planned_quantity,
        })),
        packaging: order.packaging_plans.map((pack: any) => ({
            id: pack.id,
            presentation: pack.product_variant?.presentation_label ?? 'Unidad',
            planned_units: pack.planned_units,
            actual_units: pack.actual_units ?? pack.planned_units,
        })),
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (confirm('¿Estás seguro de finalizar esta orden? Esta acción actualizará los inventarios de forma irreversible.')) {
            post(productionOrderComplete({ order: order.id }).url);
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
                    {isCompleted && (
                        <div className="flex items-center gap-2 text-green-600 font-medium">
                            <CheckCircle2 className="w-5 h-5" />
                            Finalizada el {format(new Date(order.completion_date), 'dd/MM/yyyy HH:mm')}
                        </div>
                    )}
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
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="space-y-6">
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.ingredients.map((ing: any, idx: number) => (
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
                                                    </tr>
                                                ))}
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
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {data.packaging.map((pack: any, idx: number) => (
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
                                                    </tr>
                                                ))}
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
                                    <Button 
                                        type="submit" 
                                        className="w-full mt-4" 
                                        size="lg"
                                        disabled={processing}
                                    >
                                        {processing ? 'Finalizando...' : 'Finalizar Producción'}
                                    </Button>
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
                            </CardContent>
                        </Card>
                    </div>
                </form>
            </div>
        </>
    );
}
