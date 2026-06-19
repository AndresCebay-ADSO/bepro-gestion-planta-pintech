import { Beaker } from 'lucide-react';

import { IngredientsTable } from '@/components/production/ingredients-table';
import { LineAdjustmentsPanel } from '@/components/production/line-adjustments-panel';
import { PackagingSection } from '@/components/production/packaging-section';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type {
    ProductionOrderErrors,
    ProductionOrderFormData,
    ProductionOrderIngredientFormRow,
    ProductionOrderLineAdjustment,
    ProductionOrderPackagingFormRow,
    ProductionOrderSetData,
    RawMaterialOption,
    VariantOption,
} from '@/types/production-orders';

type ResultsCardProps = {
    orderId: number;
    data: ProductionOrderFormData;
    setData: ProductionOrderSetData;
    errors: ProductionOrderErrors;
    ingredientRows: ProductionOrderIngredientFormRow[];
    packagingRows: ProductionOrderPackagingFormRow[];
    lineAdjustments: ProductionOrderLineAdjustment[];
    rawMaterials: RawMaterialOption[];
    availableVariants: VariantOption[];
    isCompleted: boolean;
    isReadOnly: boolean;
    previewLoading: boolean;
    solidsReferenceLabel: string | null;
    showCosts?: boolean;
};

export function ResultsCard({
    orderId,
    data,
    setData,
    errors,
    ingredientRows,
    packagingRows,
    lineAdjustments,
    rawMaterials,
    availableVariants,
    isCompleted,
    isReadOnly,
    previewLoading,
    solidsReferenceLabel,
    showCosts = true,
}: ResultsCardProps) {
    return (
        <Card className={isCompleted ? 'opacity-90 shadow-none' : ''}>
            <CardHeader>
                <CardTitle className="flex items-center gap-2">
                    <Beaker className="h-5 w-5 text-primary" />
                    Resultados de Fabricación
                </CardTitle>
                <CardDescription>
                    Ingrese los datos reales obtenidos al finalizar el proceso.
                    {!isCompleted && showCosts
                        ? ' Los costos se estiman en vivo desde servidor mientras editas.'
                        : ''}
                </CardDescription>
                {!isCompleted && showCosts && (
                    <p className="h-5 text-xs text-muted-foreground">
                        {previewLoading ? 'Recalculando costos...' : ''}
                    </p>
                )}
            </CardHeader>
            <CardContent className="space-y-6">
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div className="space-y-2">
                        <Label htmlFor="actual-yield">
                            Rendimiento Real (eq. gal)
                        </Label>
                        <Input
                            id="actual-yield"
                            type="number"
                            step="0.0001"
                            placeholder="Ej: 19.7500"
                            value={data.actual_yield_quantity}
                            onChange={(event) =>
                                setData(
                                    'actual_yield_quantity',
                                    event.target.value,
                                )
                            }
                            disabled={isReadOnly}
                        />
                        <p className="text-xs text-muted-foreground">
                            Debe coincidir con el equivalente envasado dentro de
                            la tolerancia.
                        </p>
                        {errors.actual_yield_quantity && (
                            <p className="text-xs text-destructive">
                                {errors.actual_yield_quantity}
                            </p>
                        )}
                    </div>
                    <div className="space-y-4 rounded-lg border border-dashed border-border/80 bg-muted/25 p-4">
                        <div className="space-y-1">
                            <p className="text-sm font-medium text-foreground">
                                Indicadores de laboratorio
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Viscosidad, molienda y sólidos se reflejan en el
                                certificado de calidad del lote al completar la
                                orden.
                            </p>
                        </div>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="viscosity">
                                    Viscosidad (KU)
                                </Label>
                                <Input
                                    id="viscosity"
                                    type="number"
                                    step="0.01"
                                    placeholder="Ej: 105.5"
                                    value={data.viscosity_ku}
                                    onChange={(event) =>
                                        setData(
                                            'viscosity_ku',
                                            event.target.value,
                                        )
                                    }
                                    disabled={isReadOnly}
                                />
                                {errors.viscosity_ku && (
                                    <p className="text-xs text-destructive">
                                        {errors.viscosity_ku}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="grinding">Molienda (HG)</Label>
                                <Input
                                    id="grinding"
                                    type="number"
                                    step="0.01"
                                    placeholder="Ej: 7.2"
                                    value={data.grinding_hg}
                                    onChange={(event) =>
                                        setData(
                                            'grinding_hg',
                                            event.target.value,
                                        )
                                    }
                                    disabled={isReadOnly}
                                />
                                {errors.grinding_hg && (
                                    <p className="text-xs text-destructive">
                                        {errors.grinding_hg}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="quality-solids">
                                    Sólidos (%)
                                </Label>
                                <Input
                                    id="quality-solids"
                                    type="number"
                                    step="0.01"
                                    placeholder="Ej: 52.4"
                                    value={data.quality_solids}
                                    onChange={(event) =>
                                        setData(
                                            'quality_solids',
                                            event.target.value,
                                        )
                                    }
                                    disabled={isReadOnly}
                                />
                                {solidsReferenceLabel && (
                                    <p className="text-xs text-muted-foreground">
                                        Referencia en ficha del producto:{' '}
                                        <span className="font-medium text-foreground">
                                            {solidsReferenceLabel}
                                        </span>
                                    </p>
                                )}
                                {errors.quality_solids && (
                                    <p className="text-xs text-destructive">
                                        {errors.quality_solids}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>

                <Separator />

                <div className="space-y-4">
                    <Label>Consumo Real de Insumos</Label>
                    <IngredientsTable
                        rows={ingredientRows}
                        data={data}
                        setData={setData}
                        isReadOnly={isReadOnly}
                        showCosts={showCosts}
                    />
                </div>

                <LineAdjustmentsPanel
                    orderId={orderId}
                    adjustments={lineAdjustments}
                    rawMaterials={rawMaterials}
                    isCompleted={isCompleted}
                    isReadOnly={isReadOnly}
                    showCosts={showCosts}
                />

                <PackagingSection
                    orderId={orderId}
                    rows={packagingRows}
                    data={data}
                    setData={setData}
                    availableVariants={availableVariants}
                    isReadOnly={isReadOnly}
                    showCosts={showCosts}
                />
            </CardContent>
        </Card>
    );
}
