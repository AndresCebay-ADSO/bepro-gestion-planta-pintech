import { FormattedNumber } from '@/components/formatted-number';
import { Card, CardContent } from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import type {
    FormNumberValue,
    ProductionOrder,
} from '@/types/production-orders';

type OrderInfoCardProps = {
    order: ProductionOrder;
    totalEquivalent: FormNumberValue;
    actualYieldQuantity: FormNumberValue;
    bulkCost: FormNumberValue;
    finishedCost: FormNumberValue;
    cifPercentage: number;
    estimatedCifValue: number;
    estimatedTotalCost: number;
    bulkCostPerUnit?: FormNumberValue | null;
    remnantQuantityGallons?: FormNumberValue;
    remnantBulkCost?: FormNumberValue | null;
    consumedRemnantCost?: FormNumberValue | null;
    showCosts?: boolean;
};

export function OrderInfoCard({
    order,
    totalEquivalent,
    actualYieldQuantity,
    bulkCost,
    finishedCost,
    cifPercentage,
    estimatedCifValue,
    estimatedTotalCost,
    bulkCostPerUnit,
    remnantQuantityGallons,
    remnantBulkCost,
    consumedRemnantCost,
    showCosts = true,
}: OrderInfoCardProps) {
    const costPerGallonWithCif =
        bulkCostPerUnit && cifPercentage > 0
            ? Number(bulkCostPerUnit) * (1 + cifPercentage / 100)
            : null;

    const hasRemnant =
        remnantQuantityGallons !== undefined &&
        remnantQuantityGallons !== '' &&
        Number(remnantQuantityGallons) > 0;

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
                <div className="flex justify-between">
                    <span className="text-muted-foreground">
                        Rend. real total (eq. gal):
                    </span>
                    <span className="font-medium">
                        <FormattedNumber
                            value={actualYieldQuantity}
                            maxDecimals={2}
                        />
                    </span>
                </div>
                {showCosts && (
                    <>
                        <Separator />
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
                        {consumedRemnantCost !== null &&
                            Number(consumedRemnantCost) > 0 && (
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Costo saldos consumidos:
                                    </span>
                                    <span className="font-medium">
                                        <FormattedNumber
                                            value={consumedRemnantCost}
                                            currency
                                            maxDecimals={2}
                                        />
                                    </span>
                                </div>
                            )}
                        {costPerGallonWithCif !== null && (
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Costo por galón (con CIF):
                                </span>
                                <span className="font-medium">
                                    <FormattedNumber
                                        value={costPerGallonWithCif}
                                        currency
                                        maxDecimals={2}
                                    />
                                </span>
                            </div>
                        )}
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
                                CIF producto (%):
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={cifPercentage}
                                    maxDecimals={2}
                                />
                                %
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                CIF estimado:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={estimatedCifValue}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Costo real producción:
                            </span>
                            <span className="font-medium">
                                <FormattedNumber
                                    value={estimatedTotalCost}
                                    currency
                                    maxDecimals={2}
                                />
                            </span>
                        </div>
                        {hasRemnant && (
                            <>
                                <Separator />
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Saldo generado:
                                    </span>
                                    <span className="font-medium">
                                        <FormattedNumber
                                            value={remnantQuantityGallons}
                                            maxDecimals={4}
                                        />{' '}
                                        gal
                                    </span>
                                </div>
                                {remnantBulkCost !== null && (
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">
                                            Costo saldo (con CIF):
                                        </span>
                                        <span className="font-medium">
                                            <FormattedNumber
                                                value={remnantBulkCost}
                                                currency
                                                maxDecimals={2}
                                            />
                                        </span>
                                    </div>
                                )}
                            </>
                        )}
                        {hasRemnant && remnantBulkCost !== null && (
                            <>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Valor total con CIF (envasado):
                                    </span>
                                    <span className="font-medium">
                                        <FormattedNumber
                                            value={estimatedTotalCost}
                                            currency
                                            maxDecimals={2}
                                        />
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Valor saldo pendiente (con CIF):
                                    </span>
                                    <span className="font-medium">
                                        <FormattedNumber
                                            value={remnantBulkCost}
                                            currency
                                            maxDecimals={2}
                                        />
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Valor total potencial orden:
                                    </span>
                                    <span className="font-bold text-primary">
                                        <FormattedNumber
                                            value={
                                                estimatedTotalCost +
                                                Number(remnantBulkCost)
                                            }
                                            currency
                                            maxDecimals={2}
                                        />
                                    </span>
                                </div>
                            </>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
