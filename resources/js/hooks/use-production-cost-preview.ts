import { useEffect, useState } from 'react';

import { previewCosts as productionOrderPreviewCosts } from '@/actions/App/Http/Controllers/ProductionOrderController';
import type {
    PreviewCostData,
    ProductionOrderIngredientFormRow,
    ProductionOrderLineAdjustment,
    ProductionOrderPackagingFormRow,
} from '@/types/production-orders';

type UseProductionCostPreviewProps = {
    orderId: number;
    ingredients: ProductionOrderIngredientFormRow[];
    packaging: ProductionOrderPackagingFormRow[];
    lineAdjustments: ProductionOrderLineAdjustment[];
    isCompleted: boolean;
    enabled?: boolean;
};

type PreviewCostPayload = {
    ingredients: Array<{ id: number; actual_quantity: number }>;
    packaging: Array<{ id: number; actual_units: number }>;
};

export function useProductionCostPreview({
    orderId,
    ingredients,
    packaging,
    lineAdjustments,
    isCompleted,
    enabled = true,
}: UseProductionCostPreviewProps) {
    const [previewCosts, setPreviewCosts] = useState<PreviewCostData | null>(
        null,
    );
    const [previewLoading, setPreviewLoading] = useState(false);

    const ingredientsSignature = JSON.stringify(
        ingredients.map((ingredient) => ({
            id: ingredient.id,
            actual_quantity: ingredient.conversion_factor
                ? (Number(ingredient.actual_quantity) || 0) * ingredient.conversion_factor
                : (Number(ingredient.actual_quantity) || 0),
        })),
    );

    const packagingSignature = JSON.stringify(
        packaging.map((pack) => ({
            id: pack.id,
            actual_units: Number(pack.actual_units) || 0,
        })),
    );

    const lineAdjustmentsSignature = JSON.stringify(
        lineAdjustments.map((adjustment) => ({
            id: adjustment.id,
            quantity: Number(adjustment.quantity) || 0,
        })),
    );

    const previewSignature = JSON.stringify({
        orderId,
        isCompleted,
        enabled,
        ingredients: ingredientsSignature,
        packaging: packagingSignature,
        lineAdjustmentsSignature,
    });

    useEffect(() => {
        if (isCompleted || !enabled) {
            return;
        }

        // Extraemos el XSRF-TOKEN de las cookies, ya que fetch no lo hace automáticamente como Axios.
        const match = document.cookie.match(
            new RegExp('(^| )XSRF-TOKEN=([^;]+)'),
        );
        const xsrfToken = match ? decodeURIComponent(match[2]) : '';

        const controller = new AbortController();
        let loadingIndicatorId: number | null = null;

        const timeoutId = window.setTimeout(async () => {
            const previewPayload: PreviewCostPayload = {
                ingredients: JSON.parse(
                    ingredientsSignature,
                ) as PreviewCostPayload['ingredients'],
                packaging: JSON.parse(
                    packagingSignature,
                ) as PreviewCostPayload['packaging'],
            };

            loadingIndicatorId = window.setTimeout(() => {
                setPreviewLoading(true);
            }, 180);

            try {
                const response = await fetch(
                    productionOrderPreviewCosts({ production_order: orderId }).url,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-XSRF-TOKEN': xsrfToken,
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        body: JSON.stringify(previewPayload),
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    return;
                }

                const payload = (await response.json()) as PreviewCostData;
                setPreviewCosts(payload);
            } catch (error) {
                if ((error as Error).name !== 'AbortError') {
                    // Preview failures should not block production form editing.
                }
            } finally {
                if (loadingIndicatorId !== null) {
                    window.clearTimeout(loadingIndicatorId);
                }

                setPreviewLoading(false);
            }
        }, 1000);

        return () => {
            controller.abort();

            if (loadingIndicatorId !== null) {
                window.clearTimeout(loadingIndicatorId);
            }

            window.clearTimeout(timeoutId);
            setPreviewLoading(false);
        };
    }, [
        ingredientsSignature,
        isCompleted,
        enabled,
        orderId,
        packagingSignature,
        previewSignature,
    ]);

    return { previewCosts, previewLoading };
}
