import { Head, router, useForm } from '@inertiajs/react';

import { useMemo, useState } from 'react';
import type { FormEvent } from 'react';

import {
    complete as productionOrderComplete,
    rejectReview as productionOrderRejectReview,
    startProduction as productionOrderStartProduction,
    submitForReview as productionOrderSubmitForReview,
} from '@/actions/App/Http/Controllers/ProductionOrderController';
import { DetailPageNav } from '@/components/detail-page-nav';
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
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { usePackagingSync } from '@/hooks/use-packaging-sync';
import { useProductionCostPreview } from '@/hooks/use-production-cost-preview';
import { index as productionOrdersIndex } from '@/routes/production-orders';
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
    returnTo,
    can,
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
    const isPending = order.status === 'pending';
    const isPendingReview = order.status === 'pending_review';
    const isFormReadOnly = isCompleted || (isPendingReview && !can.complete);
    const hasOrderData =
        orderDetails.length > 0 || orderPackagingPlans.length > 0;

    const submitMode = can.submitForReview && !isPendingReview ? 'review' : 'complete';

    const submitLabel =
        submitMode === 'review'
            ? 'Enviar a revisión'
            : isPendingReview
              ? 'Aprobar y cerrar'
              : 'Finalizar producción';

    const processingLabel =
        submitMode === 'review' ? 'Enviando...' : 'Finalizando...';

    const confirmTitle =
        submitMode === 'review'
            ? '¿Enviar orden a revisión?'
            : isPendingReview
              ? '¿Aprobar y cerrar la orden?'
              : '¿Finalizar orden de producción?';

    const confirmDescription =
        submitMode === 'review'
            ? 'Los datos quedarán registrados y producción deberá validar el cierre definitivo. No se moverá inventario todavía.'
            : 'Esta acción actualizará los inventarios de forma irreversible. Una vez finalizada, no se podrán realizar más ajustes a la orden.';

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

    const {
        data: rejectData,
        setData: setRejectData,
        post: postReject,
        processing: rejectProcessing,
        errors: rejectErrors,
        reset: resetRejectForm,
    } = useForm({ reason: '' });

    const [landingLinkCopied, setLandingLinkCopied] = useState(false);
    const [isConfirmOpen, setIsConfirmOpen] = useState(false);
    const [isStartConfirmOpen, setIsStartConfirmOpen] = useState(false);
    const [isStartingProduction, setIsStartingProduction] = useState(false);
    const [isRejectOpen, setIsRejectOpen] = useState(false);

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
        enabled: can.previewCosts,
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

    const ingredientRows = isFormReadOnly
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

    const packagingRows = isFormReadOnly
        ? orderPackagingPlans.map(mapPackagingPlanToFormRow)
        : data.packaging.map((pack) => ({
              ...pack,
              cost_price:
                  previewPackagingById.get(pack.id)?.cost_price ??
                  pack.cost_price ??
                  0,
          }));

    const totalEquivalent = isFormReadOnly
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

    const showSubmit = can.submitForReview || can.complete;
    const showReject = can.rejectReview && isPendingReview;

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setIsConfirmOpen(true);
    };

    const confirmAction = () => {
        setIsConfirmOpen(false);

        const action =
            submitMode === 'review'
                ? productionOrderSubmitForReview({ production_order: order.id })
                : productionOrderComplete({ production_order: order.id });

        post(action.url, {
            preserveScroll: true,
        });
    };

    const confirmReject = () => {
        postReject(productionOrderRejectReview({ production_order: order.id }).url, {
            preserveScroll: true,
            onSuccess: () => {
                setIsRejectOpen(false);
                resetRejectForm();
            },
        });
    };

    const confirmStartProduction = () => {
        setIsStartConfirmOpen(false);
        setIsStartingProduction(true);

        router.post(
            productionOrderStartProduction({ production_order: order.id }).url,
            {},
            {
                preserveScroll: true,
                onFinish: () => setIsStartingProduction(false),
            },
        );
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
                <DetailPageNav
                    breadcrumbs={[
                        {
                            title: 'Órdenes de Producción',
                            href:
                                returnTo ?? productionOrdersIndex().url,
                        },
                        { title: order.order_number, href: '#' },
                    ]}
                    returnTo={returnTo}
                    defaultReturnHref={productionOrdersIndex().url}
                    defaultReturnLabel="Órdenes de Producción"
                />
                <OrderHeader order={order} />

                {can.startProduction && isPending && (
                    <div className="flex flex-col gap-4 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/30 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <p className="font-medium text-amber-900 dark:text-amber-100">
                                Orden planificada
                            </p>
                            <p className="mt-1 text-sm text-amber-800 dark:text-amber-200">
                                Confirma el inicio cuando la mezcla comience en
                                planta para registrar datos operativos y enviar
                                a revisión.
                            </p>
                        </div>
                        <Button
                            type="button"
                            onClick={() => setIsStartConfirmOpen(true)}
                            disabled={isStartingProduction}
                            className="shrink-0"
                        >
                            {isStartingProduction
                                ? 'Iniciando...'
                                : 'Iniciar producción'}
                        </Button>
                    </div>
                )}

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
                            isReadOnly={isFormReadOnly}
                            previewLoading={previewLoading}
                            solidsReferenceLabel={solidsReferenceLabel}
                            showCosts={can.previewCosts}
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
                            isReadOnly={isFormReadOnly}
                            processing={processing}
                            hasOrderData={hasOrderData}
                            showSubmit={showSubmit}
                            submitLabel={submitLabel}
                            processingLabel={processingLabel}
                            showReject={showReject}
                            onReject={() => setIsRejectOpen(true)}
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
                            showCosts={can.previewCosts}
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
                                {confirmTitle}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                {confirmDescription}
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={processing}>
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmAction}
                                disabled={processing}
                            >
                                {processing ? processingLabel : 'Confirmar'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog
                    open={isStartConfirmOpen}
                    onOpenChange={setIsStartConfirmOpen}
                >
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle>
                                ¿Iniciar producción?
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                La orden {order.order_number} pasará a estado En
                                proceso. Podrás registrar datos de planta y
                                enviarla a revisión cuando esté lista.
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={isStartingProduction}>
                                Cancelar
                            </AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmStartProduction}
                                disabled={isStartingProduction}
                            >
                                {isStartingProduction
                                    ? 'Iniciando...'
                                    : 'Iniciar producción'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

                <AlertDialog
                    open={isRejectOpen}
                    onOpenChange={setIsRejectOpen}
                >
                    <AlertDialogContent>
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                confirmReject();
                            }}
                        >
                            <AlertDialogHeader>
                                <AlertDialogTitle>
                                    ¿Devolver orden a planta?
                                </AlertDialogTitle>
                                <AlertDialogDescription>
                                    Indica el motivo del rechazo para que el
                                    operador pueda corregirlo.
                                </AlertDialogDescription>
                            </AlertDialogHeader>
                            <div className="py-4">
                                <Label htmlFor="reject-reason">
                                    Motivo del rechazo
                                </Label>
                                <Textarea
                                    id="reject-reason"
                                    className="mt-2"
                                    placeholder="Describe qué debe corregirse..."
                                    value={rejectData.reason}
                                    onChange={(e) =>
                                        setRejectData('reason', e.target.value)
                                    }
                                />
                                {rejectErrors.reason && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {rejectErrors.reason}
                                    </p>
                                )}
                            </div>
                            <AlertDialogFooter>
                                <AlertDialogCancel
                                    disabled={rejectProcessing}
                                >
                                    Cancelar
                                </AlertDialogCancel>
                                <AlertDialogAction
                                    type="submit"
                                    disabled={rejectProcessing}
                                >
                                    {rejectProcessing
                                        ? 'Devolviendo...'
                                        : 'Devolver a planta'}
                                </AlertDialogAction>
                            </AlertDialogFooter>
                        </form>
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
