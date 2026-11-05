import { previewCosts as productionOrderPreviewCosts } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { useServerPreview } from '@/hooks/use-server-preview';
import type { ServerPreviewRequest } from '@/hooks/use-server-preview';
import { parseDecimal, toDecimalPayload } from '@/lib/numeric-input';
import type {
    FormNumberValue,
    PreviewCostData,
    ProductionOrderIngredientFormRow,
    ProductionOrderLineAdjustment,
    ProductionOrderPackagingFormRow,
} from '@/types/production-orders';

/** Espera tras la última tecla antes de pedir los costos. */
const PREVIEW_DELAY_MS = 1000;

type UseProductionCostPreviewProps = {
    orderId: number;
    ingredients: ProductionOrderIngredientFormRow[];
    packaging: ProductionOrderPackagingFormRow[];
    lineAdjustments: ProductionOrderLineAdjustment[];
    remnantQuantityKg: FormNumberValue;
    density: FormNumberValue;
    isCompleted: boolean;
    enabled?: boolean;
};

type PreviewCostPayload = {
    ingredients: Array<{ id: number; actual_quantity: string }>;
    packaging: Array<{
        id: number;
        actual_units: number;
        new_containers_used: string | null;
        label_raw_material_id: number | null;
        labels_used: string | null;
    }>;
    remnant_quantity_kg: string;
    density_kg_per_gallon: string | null;
};

/** Los ajustes de línea no viajan (el servidor los lee de la OP), pero al cambiar piden otra vista previa. */
type LineAdjustmentsKey = Array<{ id: number; quantity: number }>;

export function useProductionCostPreview({
    orderId,
    ingredients,
    packaging,
    lineAdjustments,
    remnantQuantityKg,
    density,
    isCompleted,
    enabled = true,
}: UseProductionCostPreviewProps) {
    const request: ServerPreviewRequest<
        PreviewCostPayload,
        LineAdjustmentsKey
    > | null =
        isCompleted || !enabled
            ? null
            : {
                  url: productionOrderPreviewCosts({
                      production_order: orderId,
                  }).url,
                  body: {
                      ingredients: ingredients.map((ingredient) => ({
                          id: ingredient.id,
                          // Lo digitado, como texto: el servidor lo convierte y calcula con bcmath.
                          actual_quantity:
                              toDecimalPayload(ingredient.actual_quantity) ||
                              '0',
                      })),
                      packaging: packaging.map((pack) => ({
                          id: pack.id,
                          actual_units: parseDecimal(pack.actual_units) ?? 0,
                          // Como texto: el servidor los calcula con bcmath. Vacío = tantos como unidades envasadas.
                          new_containers_used:
                              pack.new_containers_used === ''
                                  ? null
                                  : String(pack.new_containers_used),
                          label_raw_material_id: pack.label_raw_material_id,
                          labels_used:
                              pack.labels_used === ''
                                  ? null
                                  : String(pack.labels_used),
                      })),
                      // Como texto: el servidor calcula los galones con bcmath. Sin peso por galón, calcula sin sobrante.
                      remnant_quantity_kg:
                          toDecimalPayload(remnantQuantityKg) || '0',
                      // Solo con sobrante: sin él no se usa, y un valor inválido no debe invalidar toda la vista previa.
                      density_kg_per_gallon:
                          (parseDecimal(remnantQuantityKg) ?? 0) > 0
                              ? toDecimalPayload(density) || null
                              : null,
                  },
                  context: lineAdjustments.map((adjustment) => ({
                      id: adjustment.id,
                      quantity: Number(adjustment.quantity) || 0,
                  })),
              };

    const { result, loading, stale } = useServerPreview<
        PreviewCostData,
        PreviewCostPayload,
        LineAdjustmentsKey
    >(request, PREVIEW_DELAY_MS);

    return {
        previewCosts: result?.value ?? null,
        previewLoading: loading,
        previewStale: stale,
    };
}
