import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type FilterValue = string | undefined;

type Filters = Record<string, FilterValue>;

type UseFiltersOptions = {
    routeUrl: string;
    initialFilters: Filters;
    debounceMs?: number;
};

function cleanFilters(filters: Filters): Record<string, string> {
    const clean: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        const normalized =
            typeof value === 'string'
                ? value.trim().replace(/\s+/g, ' ')
                : value;

        if (normalized !== '' && normalized !== undefined && normalized !== null) {
            clean[key] = normalized;
        }
    }

    return clean;
}

export function useFilters({
    routeUrl,
    initialFilters,
    debounceMs = 300,
}: UseFiltersOptions) {
    const [filters, setFiltersState] = useState<Filters>(initialFilters);
    const immediateRef = useRef(false);
    const isFirstRender = useRef(true);

    const navigate = useCallback(
        (nextFilters: Filters) => {
            const clean = cleanFilters(nextFilters);
            router.get(routeUrl, clean, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [routeUrl],
    );

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;

            return;
        }

        if (immediateRef.current) {
            immediateRef.current = false;
            navigate(filters);

            return;
        }

        const timer = setTimeout(() => {
            navigate(filters);
        }, debounceMs);

        return () => clearTimeout(timer);
    }, [filters, debounceMs, navigate]);

    const setFilter = (key: string, value: FilterValue) => {
        setFiltersState((prev) => ({ ...prev, [key]: value }));
    };

    const setFilterImmediate = (key: string, value: FilterValue) => {
        immediateRef.current = true;
        setFiltersState((prev) => ({ ...prev, [key]: value }));
    };

    const clearFilters = () => {
        immediateRef.current = true;
        setFiltersState((prev) => {
            const empty: Filters = {};

            for (const key of Object.keys(prev)) {
                empty[key] = '';
            }

            return empty;
        });
    };

    return {
        filters,
        setFilter,
        setFilterImmediate,
        clearFilters,
    };
}
