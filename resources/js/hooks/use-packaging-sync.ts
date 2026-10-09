import { useEffect, useMemo, useRef } from 'react';

import { formatForInput } from '@/lib/formatters';

import type {
    ProductionOrderPackagingFormRow,
    ProductionOrderPackagingPlan,
    ProductionOrderSetData,
} from '@/types/production-orders';

/**
 * Fila del formulario a partir del plan guardado. Lo que el operario ya escribió (`edited`) se conserva; lo demás viene
 * del plan. Una sola fuente para el estado inicial de la página y para la sincronización al agregar o quitar filas.
 */
export function mapPackagingPlanToFormRow(
    pack: ProductionOrderPackagingPlan,
    edited?: ProductionOrderPackagingFormRow,
): ProductionOrderPackagingFormRow {
    return {
        id: pack.id,
        presentation: pack.product_variant?.presentation_label ?? 'Unidad',
        presentation_value: pack.product_variant?.presentation_value ?? 1,
        planned_units: pack.planned_units,
        actual_units: edited
            ? edited.actual_units
            : (pack.actual_units ?? pack.planned_units),
        cost_price: pack.cost_price ?? null,
        package_code: pack.package_code ?? null,
        new_containers_used: edited
            ? edited.new_containers_used
            : formatForInput(pack.new_containers_used),
        label_raw_material_id: edited
            ? edited.label_raw_material_id
            : (pack.label_raw_material_id ?? null),
        saved_label_raw_material_id: pack.label_raw_material_id ?? null,
        labels_used: edited
            ? edited.labels_used
            : formatForInput(pack.labels_used),
    };
}

type UsePackagingSyncProps = {
    packagingPlans: ProductionOrderPackagingPlan[];
    currentPackaging: ProductionOrderPackagingFormRow[];
    setData: ProductionOrderSetData;
};

export function usePackagingSync({
    packagingPlans,
    currentPackaging,
    setData,
}: UsePackagingSyncProps) {
    const currentPackagingRef = useRef(currentPackaging);
    const packagingPlanIds = useMemo(
        () => packagingPlans.map((pack) => pack.id).join(','),
        [packagingPlans],
    );

    useEffect(() => {
        currentPackagingRef.current = currentPackaging;
    }, [currentPackaging]);

    useEffect(() => {
        const nextPackaging = packagingPlans.map((pack) =>
            mapPackagingPlanToFormRow(
                pack,
                currentPackagingRef.current.find((item) => item.id === pack.id),
            ),
        );

        const isEquivalent =
            currentPackagingRef.current.length === nextPackaging.length &&
            nextPackaging.every((nextItem) => {
                const currentItem = currentPackagingRef.current.find(
                    (item) => item.id === nextItem.id,
                );

                if (!currentItem) {
                    return false;
                }

                return (
                    currentItem.presentation === nextItem.presentation &&
                    currentItem.presentation_value ===
                        nextItem.presentation_value &&
                    currentItem.planned_units === nextItem.planned_units &&
                    currentItem.actual_units === nextItem.actual_units &&
                    currentItem.cost_price === nextItem.cost_price &&
                    currentItem.package_code === nextItem.package_code &&
                    currentItem.new_containers_used ===
                        nextItem.new_containers_used &&
                    currentItem.label_raw_material_id ===
                        nextItem.label_raw_material_id &&
                    currentItem.labels_used === nextItem.labels_used &&
                    currentItem.saved_label_raw_material_id ===
                        nextItem.saved_label_raw_material_id
                );
            });

        if (!isEquivalent) {
            setData('packaging', nextPackaging);
        }
    }, [packagingPlanIds, packagingPlans, setData]);
}
