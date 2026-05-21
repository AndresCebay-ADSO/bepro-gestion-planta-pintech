import { useEffect, useState } from 'react';

import { previewCosts as productionOrderPreviewCosts } from '@/actions/App/Http/Controllers/ProductionOrderController';
import type { PreviewCostData, ProductionOrderIngredientFormRow, ProductionOrderPackagingFormRow } from '@/types/production-orders';

type UseProductionCostPreviewProps = {
    orderId: number;
    ingredients: ProductionOrderIngredientFormRow[];
    packaging: ProductionOrderPackagingFormRow[];
    isCompleted: boolean;
};

export function useProductionCostPreview({
    orderId,
    ingredients,
    packaging,
    isCompleted,
}: UseProductionCostPreviewProps) {
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
                const response = await fetch(productionOrderPreviewCosts({ order: orderId }).url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        ingredients: ingredients.map((ingredient) => ({
                            id: ingredient.id,
                            actual_quantity: Number(ingredient.actual_quantity) || 0,
                        })),
                        packaging: packaging.map((pack) => ({
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
                    // Preview failures should not block production form editing.
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
    }, [ingredients, isCompleted, orderId, packaging]);

    return { previewCosts, previewLoading };
}
