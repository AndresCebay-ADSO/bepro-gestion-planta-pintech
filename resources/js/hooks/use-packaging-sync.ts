import { useEffect, useMemo, useRef } from 'react';

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

export function usePackagingSync({ packagingPlans, currentPackaging, setData }: UsePackagingSyncProps) {
    const currentPackagingRef = useRef(currentPackaging);
    const packagingPlanIds = useMemo(() => packagingPlans.map((pack) => pack.id).join(','), [packagingPlans]);

    useEffect(() => {
        currentPackagingRef.current = currentPackaging;
    }, [currentPackaging]);

    useEffect(() => {
        setData(
            'packaging',
            packagingPlans.map((pack) => {
                const existingFormItem = currentPackagingRef.current.find((item) => item.id === pack.id);

                return {
                    id: pack.id,
                    presentation: pack.product_variant?.presentation_label ?? 'Unidad',
                    presentation_value: pack.product_variant?.presentation_value ?? 1,
                    planned_units: pack.planned_units,
                    actual_units: existingFormItem
                        ? existingFormItem.actual_units
                        : (pack.actual_units ?? pack.planned_units),
                    cost_price: pack.cost_price ?? null,
                };
            }),
        );
    }, [packagingPlanIds, packagingPlans, setData]);
}
