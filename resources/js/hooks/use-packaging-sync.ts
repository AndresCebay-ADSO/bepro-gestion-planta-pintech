import { useEffect, useMemo, useRef } from 'react';

import { formatForInput } from '@/lib/formatters';

import type {
    ProductionOrderPackagingFormRow,
    ProductionOrderPackagingPlan,
    ProductionOrderSetData,
} from '@/types/production-orders';

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
        const nextPackaging = packagingPlans.map((pack) => {
            const existingFormItem = currentPackagingRef.current.find(
                (item) => item.id === pack.id,
            );

            // Lo que el operario ya escribió se conserva; lo demás viene del plan guardado.
            return {
                id: pack.id,
                presentation:
                    pack.product_variant?.presentation_label ?? 'Unidad',
                presentation_value:
                    pack.product_variant?.presentation_value ?? 1,
                planned_units: pack.planned_units,
                actual_units: existingFormItem
                    ? existingFormItem.actual_units
                    : (pack.actual_units ?? pack.planned_units),
                cost_price: pack.cost_price ?? null,
                package_code: pack.package_code ?? null,
                new_containers_used: existingFormItem
                    ? existingFormItem.new_containers_used
                    : formatForInput(pack.new_containers_used),
                label_raw_material_id: existingFormItem
                    ? existingFormItem.label_raw_material_id
                    : (pack.label_raw_material_id ?? null),
                saved_label_raw_material_id: pack.label_raw_material_id ?? null,
                labels_used: existingFormItem
                    ? existingFormItem.labels_used
                    : formatForInput(pack.labels_used),
            };
        });

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
