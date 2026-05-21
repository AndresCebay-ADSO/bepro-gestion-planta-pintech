import { Clock, User as UserIcon } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';
import type {
    ProductionOrderErrors,
    ProductionOrderFormData,
    ProductionOrderSetData,
} from '@/types/production-orders';

type ControlCardProps = {
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    errors: ProductionOrderErrors;
    isCompleted: boolean;
    processing: boolean;
    hasOrderData: boolean;
};

export function ControlCard({
    data,
    setData,
    errors,
    isCompleted,
    processing,
    hasOrderData,
}: ControlCardProps) {
    return (
        <Card>
            <CardHeader>
                <CardTitle className="flex items-center gap-2 text-base">
                    <Clock className="h-4 w-4 text-primary" />
                    Tiempos y Control
                </CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="responsible" className="flex items-center gap-1">
                        <UserIcon className="h-3 w-3" /> Responsable
                    </Label>
                    <Input
                        id="responsible"
                        placeholder="Nombre del operario"
                        value={data.responsible_name}
                        onChange={(event) => setData('responsible_name', event.target.value)}
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
                        onChange={(event) => setData('spillage_quantity', event.target.value)}
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
                            onChange={(event) => setData('agitation_start_time', event.target.value)}
                            disabled={isCompleted}
                        />
                    </div>
                    <div className="space-y-2">
                        <Label className="text-xs">Fin Empaque</Label>
                        <Input
                            type="datetime-local"
                            className="h-9 text-xs"
                            value={data.packaging_end_time}
                            onChange={(event) => setData('packaging_end_time', event.target.value)}
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
                        onChange={(event) => setData('notes', event.target.value)}
                        disabled={isCompleted}
                    />
                </div>

                {!isCompleted && (
                    <>
                        {errors.packaging && <p className="text-xs text-destructive">{errors.packaging}</p>}
                        {errors.ingredients && <p className="text-xs text-destructive">{errors.ingredients}</p>}
                        {errors.line_adjustments && (
                            <p className="text-xs text-destructive">{errors.line_adjustments}</p>
                        )}
                    </>
                )}

                {!isCompleted && (
                    <Button type="submit" className="mt-4 w-full" size="lg" disabled={processing || !hasOrderData}>
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
    );
}
