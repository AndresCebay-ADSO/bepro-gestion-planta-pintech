import { Head, router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import Pagination from '@/components/ui/pagination';
import { index as adminCostsIndex, update as adminCostsUpdate } from '@/routes/admin/costs';
import type { PaginationLink } from '@/types/ui';

type ProductRow = {
    id: number;
    code: string | null;
    name: string;
    current_cost: number | null;
    cif_percentage: number | null;
    current_price: number | null;
    sales_margin: number | null;
};

type Props = {
    products: {
        data: ProductRow[];
        links: PaginationLink[];
    };
    can: {
        update_margin: boolean;
    };
    filters: {
        search?: string;
    };
};

type MarginState = {
    margin: string;
    price: string;
    lastEdited: 'margin' | 'price' | null;
};

function calculateSalesPrice(
    currentPrice: number | null,
    salesMargin: number | null,
): number | null {
    if (currentPrice === null || currentPrice === undefined) {
        return null;
    }

    const margin = salesMargin ?? 0;

    return parseFloat((currentPrice * (1 + margin / 100)).toFixed(2));
}

function calculateMargin(
    currentPrice: number,
    salesPrice: number,
): number {
    return parseFloat((((salesPrice / currentPrice) - 1) * 100).toFixed(2));
}

function buildInitialMargins(products: ProductRow[]): Record<number, MarginState> {
    const initial: Record<number, MarginState> = {};

    products.forEach((product) => {
        const margin = product.sales_margin ?? '';
        const price = calculateSalesPrice(product.current_price, product.sales_margin) ?? '';

        initial[product.id] = {
            margin: margin === '' ? '' : String(margin),
            price: price === '' ? '' : String(price),
            lastEdited: null,
        };
    });

    return initial;
}

export default function CostsIndex({
    products: productsData,
    can,
    filters,
}: Props) {
    const { data, setData, get } = useForm({
        search: filters.search ?? '',
    });

    const [margins, setMargins] = useState<Record<number, MarginState>>(() =>
        buildInitialMargins(productsData.data),
    );

    const [savingIds, setSavingIds] = useState<Set<number>>(new Set());
    const [errors, setErrors] = useState<Record<number, string | null>>({});

    useEffect(() => {
        setMargins(buildInitialMargins(productsData.data));
        setErrors({});
    }, [productsData.data]);

    const handleSearch = () => {
        get(adminCostsIndex().url, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const syncMargin = useCallback(
        (productId: number, marginValue: string) => {
            const product = productsData.data.find((p) => p.id === productId);
            if (!product) return;

            const numericValue = marginValue === '' ? null : parseFloat(marginValue);
            const price =
                numericValue === null || isNaN(numericValue)
                    ? ''
                    : String(
                          calculateSalesPrice(
                              product.current_price,
                              numericValue,
                          ) ?? '',
                      );

            setMargins((prev) => ({
                ...prev,
                [productId]: {
                    margin: marginValue,
                    price,
                    lastEdited: 'margin',
                },
            }));

            // Clear error when user starts editing
            setErrors((prev) => ({
                ...prev,
                [productId]: null,
            }));
        },
        [productsData.data],
    );

    const syncPrice = useCallback(
        (productId: number, priceValue: string) => {
            const product = productsData.data.find((p) => p.id === productId);
            if (!product || !product.current_price || product.current_price <= 0) return;

            const price = parseFloat(priceValue) || 0;
            const margin = priceValue === '' ? '' : String(calculateMargin(product.current_price, price));

            setMargins((prev) => ({
                ...prev,
                [productId]: {
                    margin,
                    price: priceValue,
                    lastEdited: 'price',
                },
            }));

            // Clear error when user starts editing
            setErrors((prev) => ({
                ...prev,
                [productId]: null,
            }));
        },
        [productsData.data],
    );

    const handleSave = useCallback(
        (productId: number) => {
            const state = margins[productId];
            if (!state || !state.lastEdited) return;

            const product = productsData.data.find((p) => p.id === productId);
            if (!product) return;

            let payload: Record<string, number | null> = {};

            if (state.lastEdited === 'margin') {
                const numericValue = state.margin === '' ? null : parseFloat(state.margin);

                if (numericValue === null || isNaN(numericValue)) {
                    setErrors((prev) => ({
                        ...prev,
                        [productId]: 'El margen debe ser un número válido.',
                    }));

                    return;
                }

                if (numericValue < 0 || numericValue > 500) {
                    setErrors((prev) => ({
                        ...prev,
                        [productId]: 'El margen debe estar entre 0% y 500%.',
                    }));

                    return;
                }

                payload = { sales_margin: numericValue };
            } else {
                const numericValue = state.price === '' ? null : parseFloat(state.price);

                if (numericValue === null || isNaN(numericValue)) {
                    setErrors((prev) => ({
                        ...prev,
                        [productId]: 'El precio debe ser un número válido.',
                    }));

                    return;
                }

                if (numericValue < 0) {
                    setErrors((prev) => ({
                        ...prev,
                        [productId]: 'El precio no puede ser negativo.',
                    }));

                    return;
                }

                payload = { sales_price: numericValue };
            }

            // Clear error before attempting save
            setErrors((prev) => ({
                ...prev,
                [productId]: null,
            }));

            setSavingIds((prev) => new Set(prev).add(productId));

            router.patch(
                adminCostsUpdate({ product: productId }).url,
                payload,
                {
                    preserveState: true,
                    preserveScroll: true,
                    onSuccess: () => {
                        setSavingIds((prev) => {
                            const next = new Set(prev);

                            next.delete(productId);

                            return next;
                        });

                        // Reset lastEdited after successful save
                        setMargins((prev) => ({
                            ...prev,
                            [productId]: {
                                ...prev[productId],
                                lastEdited: null,
                            },
                        }));
                    },
                    onError: (errorBag) => {
                        setSavingIds((prev) => {
                            const next = new Set(prev);

                            next.delete(productId);

                            return next;
                        });

                        const serverError =
                            errorBag?.sales_price ||
                            errorBag?.sales_margin ||
                            'Error al guardar. Intente nuevamente.';

                        setErrors((prev) => ({
                            ...prev,
                            [productId]: Array.isArray(serverError)
                                ? serverError[0]
                                : serverError,
                        }));
                    },
                },
            );
        },
        [margins, productsData.data],
    );

    const handleBlur = useCallback(
        (productId: number) => {
            handleSave(productId);
        },
        [handleSave],
    );

    const handleKeyDown = useCallback(
        (e: React.KeyboardEvent<HTMLInputElement>, productId: number) => {
            if (e.key === 'Enter') {
                (e.target as HTMLInputElement).blur();
            }
        },
        [handleBlur],
    );

    const hasCurrentPrice = (product: ProductRow): boolean => {
        return product.current_price !== null && product.current_price > 0;
    };

    return (
        <>
            <Head title="Costos" />

            <div className="space-y-4 p-6">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Costos
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Gestión de costos y márgenes de venta por producto.
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div className="relative w-full max-w-sm">
                        <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Buscar producto..."
                            value={data.search}
                            onChange={(e) => {
                                setData('search', e.target.value);
                            }}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    handleSearch();
                                }
                            }}
                            className="pl-10"
                        />
                    </div>
                    <Button variant="outline" onClick={handleSearch}>
                        <Search className="mr-2 h-4 w-4" />
                        Buscar
                    </Button>
                </div>

                <div className="overflow-hidden rounded-xl border border-border bg-card shadow-sm">
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="border-b border-border bg-muted/50">
                                <tr>
                                    <th className="p-4 text-left font-medium">
                                        Producto
                                    </th>
                                    <th className="p-4 text-right font-medium">
                                        Costo Actual
                                    </th>
                                    <th className="p-4 text-right font-medium">
                                        CIF %
                                    </th>
                                    <th className="p-4 text-right font-medium">
                                        Precio Interno
                                    </th>
                                    <th className="p-4 text-right font-medium">
                                        Margen Venta %
                                    </th>
                                    <th className="p-4 text-right font-medium">
                                        Precio Venta
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {productsData.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6}
                                            className="p-8 text-center text-sm text-muted-foreground"
                                        >
                                            No hay productos registrados.
                                        </td>
                                    </tr>
                                ) : (
                                    productsData.data.map((product) => {
                                        const state = margins[product.id] ?? {
                                            margin: '',
                                            price: '',
                                            lastEdited: null,
                                        };

                                        return (
                                            <tr
                                                key={product.id}
                                                className="border-b border-border/50 transition-colors hover:bg-muted/30"
                                            >
                                                <td className="p-4">
                                                    <div className="font-medium text-foreground">
                                                        {product.name}
                                                    </div>
                                                    {product.code && (
                                                        <div className="text-xs text-muted-foreground font-mono">
                                                            {product.code}
                                                        </div>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <FormattedNumber
                                                        value={product.current_cost}
                                                        currency
                                                        maxDecimals={2}
                                                    />
                                                </td>
                                                <td className="p-4 text-right">
                                                    <FormattedNumber
                                                        value={product.cif_percentage}
                                                        maxDecimals={2}
                                                    />
                                                    {product.cif_percentage !== null && (
                                                        <span className="text-xs text-muted-foreground ml-1">
                                                            %
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-4 text-right">
                                                    <FormattedNumber
                                                        value={product.current_price}
                                                        currency
                                                        maxDecimals={2}
                                                    />
                                                </td>
                                                <td className="p-4 text-right">
                                                    {can.update_margin ? (
                                                        <div className="flex flex-col items-end gap-1">
                                                            <div className="flex items-center justify-end gap-2">
                                                                <Input
                                                                    type="number"
                                                                    min={0}
                                                                    max={500}
                                                                    step={0.01}
                                                                    value={state.margin}
                                                                    onChange={(e) =>
                                                                        syncMargin(
                                                                            product.id,
                                                                            e.target.value,
                                                                        )
                                                                    }
                                                                    onBlur={() =>
                                                                        handleBlur(product.id)
                                                                    }
                                                                    onKeyDown={(e) =>
                                                                        handleKeyDown(
                                                                            e,
                                                                            product.id,
                                                                        )
                                                                    }
                                                                    className="w-20 text-right"
                                                                />
                                                                <span className="text-xs text-muted-foreground">
                                                                    %
                                                                </span>
                                                                {savingIds.has(
                                                                    product.id,
                                                                ) && (
                                                                    <span className="text-xs text-muted-foreground animate-pulse">
                                                                        Guardando...
                                                                    </span>
                                                                )}
                                                            </div>
                                                            {errors[product.id] && (
                                                                <span className="text-xs text-red-500">
                                                                    {errors[product.id]}
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <FormattedNumber
                                                            value={
                                                                product.sales_margin
                                                            }
                                                            maxDecimals={2}
                                                        />
                                                    )}
                                                </td>
                                                <td className="p-4 text-right">
                                                    {can.update_margin &&
                                                    hasCurrentPrice(product) ? (
                                                        <div className="flex items-center justify-end gap-2">
                                                            <Input
                                                                type="number"
                                                                min={0}
                                                                step={0.01}
                                                                value={state.price}
                                                                onChange={(e) =>
                                                                    syncPrice(
                                                                        product.id,
                                                                        e.target.value,
                                                                    )
                                                                }
                                                                onBlur={() =>
                                                                    handleBlur(product.id)
                                                                }
                                                                onKeyDown={(e) =>
                                                                    handleKeyDown(
                                                                        e,
                                                                        product.id,
                                                                    )
                                                                }
                                                                className="w-28 text-right"
                                                            />
                                                            {savingIds.has(
                                                                product.id,
                                                            ) && (
                                                                <span className="text-xs text-muted-foreground animate-pulse">
                                                                    Guardando...
                                                                </span>
                                                            )}
                                                        </div>
                                                    ) : (
                                                        <FormattedNumber
                                                            value={calculateSalesPrice(
                                                                product.current_price,
                                                                product.sales_margin,
                                                            )}
                                                            currency
                                                            maxDecimals={2}
                                                            bold
                                                        />
                                                    )}
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                <div className="flex justify-center">
                    <Pagination links={productsData.links} />
                </div>
            </div>
        </>
    );
}
