import { Head, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';

import { complete as productionOrderComplete } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { ControlCard } from '@/components/production/control-card';
import { OrderHeader } from '@/components/production/order-header';
import { OrderInfoCard } from '@/components/production/order-info-card';
import { QrCard } from '@/components/production/qr-card';
import { ResultsCard } from '@/components/production/results-card';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { usePackagingSync } from '@/hooks/use-packaging-sync';
import { useProductionCostPreview } from '@/hooks/use-production-cost-preview';
import type {
    PreviewCostData,
    ProductionOrderDetail,
    ProductionOrderFormData,
    ProductionOrderPackagingPlan,
    ProductionOrderShowProps,
} from '@/types/production-orders';

export default function ProductionOrderShow({
    order,
    rawMaterials,
    availableVariants,
}: ProductionOrderShowProps) {
    const orderDetails = useMemo(() => order.details ?? [], [order.details]);
    const orderPackagingPlans = useMemo(
        () => order.packaging_plans ?? [],
        [order.packaging_plans],
    );
    const lineAdjustments = useMemo(
        () => order.line_adjustments ?? [],
        [order.line_adjustments],
    );

    const isCompleted = order.status === 'completed';
    const hasOrderData =
        orderDetails.length > 0 || orderPackagingPlans.length > 0;

    const { data, setData, post, processing, errors } =
        useForm<ProductionOrderFormData>({
            actual_yield_quantity: order.actual_quantity ?? order.quantity,
            viscosity_ku: order.viscosity_ku ?? '',
            grinding_hg: order.grinding_hg ?? '',
            quality_solids: order.quality_solids ?? '',
            agitation_start_time: order.agitation_start_time ?? '',
            agitation_end_time: order.agitation_end_time ?? '',
            packaging_start_time: order.packaging_start_time ?? '',
            packaging_end_time: order.packaging_end_time ?? '',
            responsible_name: order.responsible_name ?? '',
            spillage_quantity: order.spillage_quantity ?? 0,
            notes: order.notes ?? '',
            ingredients: orderDetails.map(mapDetailToIngredientFormRow),
            packaging: orderPackagingPlans.map(mapPackagingPlanToFormRow),
        });

    const [landingLinkCopied, setLandingLinkCopied] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);

    usePackagingSync({
        packagingPlans: orderPackagingPlans,
        currentPackaging: data.packaging,
        setData,
    });

    const { previewCosts, previewLoading } = useProductionCostPreview({
        orderId: order.id,
        ingredients: data.ingredients,
        packaging: data.packaging,
        lineAdjustments,
        isCompleted,
    });

    const landingFullUrl = useMemo(() => {
        if (!order.qr_landing_url) {
            return '';
        }

        if (typeof window === 'undefined') {
            return order.qr_landing_url;
        }

        return `${window.location.origin}${order.qr_landing_url}`;
    }, [order.qr_landing_url]);

    const solidsReferenceLabel = useMemo(() => {
        const lower = order.product?.quality_solids_lower;
        const upper = order.product?.quality_solids_upper;

        if (lower == null && upper == null) {
            return null;
        }

        if (lower != null && upper != null) {
            return `${lower}% – ${upper}%`;
        }

        if (lower != null) {
            return `≥ ${lower}%`;
        }

        return `≤ ${upper}%`;
    }, [
        order.product?.quality_solids_lower,
        order.product?.quality_solids_upper,
    ]);

    const previewIngredientsById = useMemo(() => {
        if (!previewCosts) {
            return new Map<number, PreviewCostData['ingredients'][number]>();
        }

        return new Map(
            previewCosts.ingredients.map((ingredient) => [
                ingredient.id,
                ingredient,
            ]),
        );
    }, [previewCosts]);

    const previewPackagingById = useMemo(() => {
        if (!previewCosts) {
            return new Map<number, PreviewCostData['packaging'][number]>();
        }

        return new Map(previewCosts.packaging.map((pack) => [pack.id, pack]));
    }, [previewCosts]);

    const ingredientRows = isCompleted
        ? orderDetails.map(mapDetailToIngredientFormRow)
        : data.ingredients.map((ingredient) => ({
              ...ingredient,
              unit_cost:
                  previewIngredientsById.get(ingredient.id)?.unit_cost ??
                  ingredient.unit_cost ??
                  0,
              total_cost:
                  previewIngredientsById.get(ingredient.id)?.total_cost ??
                  ingredient.total_cost ??
                  0,
          }));

    const packagingRows = isCompleted
        ? orderPackagingPlans.map(mapPackagingPlanToFormRow)
        : data.packaging.map((pack) => ({
              ...pack,
              cost_price:
                  previewPackagingById.get(pack.id)?.cost_price ??
                  pack.cost_price ??
                  0,
          }));

    const totalEquivalent = isCompleted
        ? packagingRows.reduce((sum, pack) => {
              return (
                  sum +
                  (Number(pack.actual_units) || 0) *
                      (Number(pack.presentation_value) || 0)
              );
          }, 0)
        : (previewCosts?.total_equivalent ?? 0);

    const pendingBulkCost = previewCosts?.total_bulk_cost ?? 0;
    const pendingFinishedCost = previewCosts?.total_finished_cost ?? 0;
    const marginPercentage = Number(order.product?.profit_margin ?? 0);
    const activeFinishedCost = isCompleted
        ? Number(order.total_finished_cost || 0)
        : Number(pendingFinishedCost || 0);
    const estimatedMarginValue = activeFinishedCost * (marginPercentage / 100);
    const estimatedTargetValue = activeFinishedCost + estimatedMarginValue;

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsConfirmOpen(true);
    };

    const confirmCompletion = () => {
        setIsConfirmOpen(false);
        post(productionOrderComplete({ order: order.id }).url, {
            preserveScroll: true,
        });
    };

    const handleCopyLandingLink = () => {
        if (!landingFullUrl) {
            return;
        }

        void navigator.clipboard.writeText(landingFullUrl).then(() => {
            setLandingLinkCopied(true);
            window.setTimeout(() => setLandingLinkCopied(false), 2000);
        });
    };

    return (
        <>
            <Head title={`Orden ${order.order_number}`} />
            <div className="mx-auto max-w-7xl space-y-6 p-6">
                <OrderHeader order={order} isCompleted={isCompleted} />

                <form
                    onSubmit={handleSubmit}
                    className="grid grid-cols-1 gap-6 lg:grid-cols-3"
                >
                    <div className="space-y-6 lg:col-span-2">
                        <ResultsCard
                            orderId={order.id}
                            data={data}
                            setData={setData}
                            errors={errors}
                            ingredientRows={ingredientRows}
                            packagingRows={packagingRows}
                            lineAdjustments={lineAdjustments}
                            rawMaterials={rawMaterials}
                            availableVariants={availableVariants}
                            isCompleted={isCompleted}
                            previewLoading={previewLoading}
                            solidsReferenceLabel={solidsReferenceLabel}
                        />
                    </div>

                    <div className="space-y-6">
                        {isCompleted && order.qr_landing_url && (
                            <QrCard
                                order={order}
                                landingFullUrl={landingFullUrl}
                                landingLinkCopied={landingLinkCopied}
                                onCopyLandingLink={handleCopyLandingLink}
                            />
                        )}

                        <ControlCard
                            data={data}
                            setData={setData}
                            errors={errors}
                            isCompleted={isCompleted}
                            processing={processing}
                            hasOrderData={hasOrderData}
                        />

                        <OrderInfoCard
                            order={order}
                            totalEquivalent={totalEquivalent}
                            bulkCost={
                                isCompleted
                                    ? (order.total_bulk_cost ?? 0)
                                    : pendingBulkCost
                            }
                            finishedCost={
                                isCompleted
                                    ? (order.total_finished_cost ?? 0)
                                    : pendingFinishedCost
                            }
                            marginPercentage={marginPercentage}
                            estimatedMarginValue={estimatedMarginValue}
                            estimatedTargetValue={estimatedTargetValue}
                        />
                    </div>
                </form>

                <AlertDialog
                    open={isConfirmOpen}
                    onOpenChange={setIsConfirmOpen}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                ¿Finalizar orden de producción?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                Esta acción actualizará los inventarios de forma
                                irreversible. Una vez finalizada, no se podrán
                                realizar más ajustes a la orden.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={processing}>
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmCompletion}
                                disabled={processing}
                            >
                                {processing ? 'Finalizando...' : 'Confirmar'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>
            </div>
        </>
    );
}

function mapDetailToIngredientFormRow(detail: ProductionOrderDetail) {
    return {
        id: detail.id,
        raw_material_name: detail.raw_material?.code,
        planned_quantity: detail.planned_quantity,
        actual_quantity: detail.actual_quantity ?? detail.planned_quantity,
        unit_cost: detail.unit_cost ?? 0,
        total_cost: detail.total_cost ?? 0,
    };
}

function mapPackagingPlanToFormRow(pack: ProductionOrderPackagingPlan) {
    return {
        id: pack.id,
        presentation: pack.product_variant?.presentation_label ?? 'Unidad',
        presentation_value: pack.product_variant?.presentation_value ?? 1,
        planned_units: pack.planned_units,
        actual_units: pack.actual_units ?? pack.planned_units,
        cost_price: pack.cost_price ?? null,
    };
}
