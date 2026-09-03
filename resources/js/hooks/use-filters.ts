import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';

type FilterValue = string | null | undefined;

type Filters = Record<string, FilterValue>;

type UseFiltersOptions = {
    routeUrl: string;
    initialFilters: Filters;
    defaultFilters?: Filters;
    debounceMs?: number;
};

function cleanFilters(filters: Filters): Record<string, string> {
    const clean: Record<string, string> = {};

    for (const [key, value] of Object.entries(filters)) {
        const normalized =
            typeof value === 'string'
                ? value.trim().replace(/\s+/g, ' ')
                : value;

        if (
            normalized !== '' &&
            normalized !== undefined &&
            normalized !== null &&
            normalized !== '__all__'
        ) {
            clean[key] = normalized;
        }
    }

    return clean;
}

export function useFilters({
    routeUrl,
    initialFilters,
    defaultFilters,
    debounceMs = 300,
}: UseFiltersOptions) {
    const [filters, setFiltersState] = useState<Filters>(initialFilters);
    const filtersRef = useRef<Filters>(initialFilters);
    const isFirstRender = useRef(true);
    const timerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
    const lastNavigated = useRef<string>(
        JSON.stringify(cleanFilters(initialFilters)),
    );

    useEffect(() => {
        filtersRef.current = filters;
    }, [filters]);

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

        const clean = cleanFilters(filters);
        const key = JSON.stringify(clean);

        if (lastNavigated.current === key) {
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

    const setFilter = useCallback(
        (
            keyOrUpdates: string | Record<string, FilterValue>,
            value?: FilterValue,
        ) => {
            const updates =
                typeof keyOrUpdates === 'string'
                    ? { [keyOrUpdates]: value }
                    : keyOrUpdates;

            const next = { ...filtersRef.current, ...updates };
            filtersRef.current = next;
            setFiltersState(next);
        },
        [],
    );

    const setFilterImmediate = useCallback(
        (
            keyOrUpdates: string | Record<string, FilterValue>,
            value?: FilterValue,
        ) => {
            if (timerRef.current) {
                clearTimeout(timerRef.current);
                timerRef.current = null;
            }

            const updates =
                typeof keyOrUpdates === 'string'
                    ? { [keyOrUpdates]: value }
                    : keyOrUpdates;

            const next = { ...filtersRef.current, ...updates };
            filtersRef.current = next;
            setFiltersState(next);
            navigate(next);
        },
        [navigate],
    );

    const clearFilters = useCallback(() => {
        if (timerRef.current) {
            clearTimeout(timerRef.current);
            timerRef.current = null;
        }

        const defaults =
            defaultFilters ??
            Object.fromEntries(
                Object.keys(filtersRef.current).map((k) => [k, '']),
            );

        filtersRef.current = defaults;
        setFiltersState(defaults);
        navigate(defaults);
    }, [defaultFilters, navigate]);

    return {
        filters,
        setFilter,
        setFilterImmediate,
        clearFilters,
    };
}
