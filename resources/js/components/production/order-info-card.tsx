import { FormattedNumber } from '@/components/formatted-number';
import { Card, CardContent } from '@/components/ui/card';
import type {
    FormNumberValue,
    ProductionOrder,
} from '@/types/production-orders';

type OrderInfoCardProps = {
    order: ProductionOrder;
    totalEquivalent: FormNumberValue;
    bulkCost: FormNumberValue;
    finishedCost: FormNumberValue;
    marginPercentage: number;
    estimatedMarginValue: number;
    estimatedTargetValue: number;
    showCosts?: boolean;
};

export function OrderInfoCard({
    order,
    totalEquivalent,
    bulkCost,
    finishedCost,
    marginPercentage,
    estimatedMarginValue,
    estimatedTargetValue,
    showCosts = true,
}: OrderInfoCardProps) {
    return (
        <Card className="bg-muted/40">
            <CardContent className="space-y-2 p-4 text-xs">
                <div className="flex justify-between">
                    <span className="text-muted-foreground">Fórmula:</span>
                    <span className="font-medium">
                        v{order.formula?.version}
                    </span>
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
                    <span className="text-muted-foreground">
                        Rend. envasado (eq. gal):
                    </span>
                    <span className="font-medium">
                        <FormattedNumber
                            value={totalEquivalent}
                            maxDecimals={2}
                        />
                    </span>
                </div>
                {showCosts && (
                    <>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Costo granel acumulado:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={bulkCost}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Costo terminado total:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={finishedCost}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Margen producto (%):
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={marginPercentage}
                                    maxDecimals={2}
                                />
                                %
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Margen estimado:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={estimatedMarginValue}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Valor objetivo c/margen:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={estimatedTargetValue}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                    </>
                )}
            </CardContent>
        </Card>
    );
}
