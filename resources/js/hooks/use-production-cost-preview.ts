import { useEffect, useState } from 'react';

import { previewCosts as productionOrderPreviewCosts } from '@/actions/App/Http/Controllers/ProductionOrderController';
import type {
    PreviewCostData,
    ProductionOrderIngredientFormRow,
    ProductionOrderLineAdjustment,
    ProductionOrderPackagingFormRow,
} from '@/types/production-orders';

/** Por qué los costos en pantalla no corresponden a lo escrito (null si están al día). */
export type PreviewStaleReason = 'invalid' | 'failed' | null;

type UseProductionCostPreviewProps = {
    orderId: number;
    ingredients: ProductionOrderIngredientFormRow[];
    packaging: ProductionOrderPackagingFormRow[];
    lineAdjustments: ProductionOrderLineAdjustment[];
    remnantQuantityGallons: string | number;
    isCompleted: boolean;
    enabled?: boolean;
};

type PreviewCostPayload = {
    ingredients: Array<{ id: number; actual_quantity: number }>;
    packaging: Array<{
        id: number;
        actual_units: number;
        new_containers_used: string | null;
        label_raw_material_id: number | null;
        labels_used: string | null;
    }>;
    remnant_quantity_gallons: number;
};

export function useProductionCostPreview({
    orderId,
    ingredients,
    packaging,
    lineAdjustments,
    remnantQuantityGallons,
    isCompleted,
    enabled = true,
}: UseProductionCostPreviewProps) {
    const [previewCosts, setPreviewCosts] = useState<PreviewCostData | null>(
        null,
    );
    const [previewLoading, setPreviewLoading] = useState(false);
    // Si la última petición falló, los costos en pantalla son de la última vista previa correcta: 'invalid' si el
    // servidor rechazó los datos (422), 'failed' por cualquier otro fallo (límite de peticiones, error, sin conexión).
    const [previewStale, setPreviewStale] = useState<PreviewStaleReason>(null);

    const ingredientsSignature = JSON.stringify(
        ingredients.map((ingredient) => ({
            id: ingredient.id,
            actual_quantity: ingredient.conversion_factor
                ? (Number(ingredient.actual_quantity) || 0) *
                  ingredient.conversion_factor
                : Number(ingredient.actual_quantity) || 0,
        })),
    );

    const packagingSignature = JSON.stringify(
        packaging.map((pack) => ({
            id: pack.id,
            actual_units: Number(pack.actual_units) || 0,
            // Como texto: el servidor los calcula con bcmath. Vacío = tantos como unidades envasadas.
            new_containers_used:
                pack.new_containers_used === ''
                    ? null
                    : String(pack.new_containers_used),
            label_raw_material_id: pack.label_raw_material_id,
            labels_used:
                pack.labels_used === '' ? null : String(pack.labels_used),
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
        remnantQuantityGallons,
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
                remnant_quantity_gallons: Number(remnantQuantityGallons) || 0,
            };

            loadingIndicatorId = window.setTimeout(() => {
                setPreviewLoading(true);
            }, 180);

            try {
                const response = await fetch(
                    productionOrderPreviewCosts({ production_order: orderId })
                        .url,
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
                    setPreviewStale(
                        response.status === 422 ? 'invalid' : 'failed',
                    );

                    return;
                }

                const payload = (await response.json()) as PreviewCostData;
                setPreviewCosts(payload);
                setPreviewStale(null);
            } catch (error) {
                // Una petición cancelada (el operario siguió escribiendo) no es un fallo: la reemplaza la siguiente.
                // Un fallo de la vista previa no bloquea el formulario, pero sí avisa que los costos no están al día.
                if ((error as Error).name !== 'AbortError') {
                    setPreviewStale('failed');
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
        remnantQuantityGallons,
    ]);

    return { previewCosts, previewLoading, previewStale };
}
