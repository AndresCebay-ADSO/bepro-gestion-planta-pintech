import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type FilterValue = string | null | undefined;

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

        if (normalized !== '' && normalized !== undefined && normalized !== null && normalized !== '__all__') {
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
    const isFirstRender = useRef(true);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lastNavigated = useRef<string>('');

    const navigate = useCallback(
        (nextFilters: Filters) => {
            const clean = cleanFilters(nextFilters);
            const key = JSON.stringify(clean);

            if (lastNavigated.current === key) {
                return;
            }

            lastNavigated.current = key;
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

        timerRef.current = setTimeout(() => {
            navigate(filters);
        }, debounceMs);

        return () => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
            }
        };
    }, [filters, debounceMs, navigate]);

    const setFilter = (key: string, value: FilterValue) => {
        setFiltersState((prev) => ({ ...prev, [key]: value }));
    };

    const setFilterImmediate = (key: string, value: FilterValue) => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }

        setFiltersState((prev) => {
            const next = { ...prev, [key]: value };
            navigate(next);

            return next;
        });
    };

    const clearFilters = () => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
        }

        setFiltersState((prev) => {
            const empty: Filters = {};

            for (const key of Object.keys(prev)) {
                empty[key] = '';
            }

            navigate(empty);

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
