import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { withDecimalPayloads } from '@/lib/numeric-input';
import {
    PRODUCT_DECIMAL_FIELDS,
    PRODUCT_DECIMAL_LIMITS,
} from '@/lib/product-form';
import {
    show as productsShow,
    index as productsIndex,
} from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    product: {
        id: number;
        name: string;
        description: string | null;
        category_id: number | null;
        unit_of_measure_id: number | null;
        current_cost?: string | null;
        cif_percentage?: string | null;
        current_price?: string | null;
        price_threshold?: string | null;
        quality_viscosity_lower: number | string | null;
        quality_viscosity_upper: number | string | null;
        quality_fineness_lower: number | string | null;
        quality_fineness_upper: number | string | null;
        quality_solids_lower: number | string | null;
        quality_solids_upper: number | string | null;
        is_active: boolean;
    };
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean; viewCosts: boolean; deactivate: boolean };
    hasPriceReferenceFormula?: boolean;
};

type ProductForm = {
    name: string;
    description: string;
    category_id: string;
    unit_of_measure_id: string;
    cif_percentage: string;
    price_threshold: string;
    quality_viscosity_lower: string;
    quality_viscosity_upper: string;
    quality_fineness_lower: string;
    quality_fineness_upper: string;
    quality_solids_lower: string;
    quality_solids_upper: string;
    is_active: boolean;
};

export default function ProductsEdit({
    product,
    categories,
    units,
    can,
    hasPriceReferenceFormula,
}: Props) {
    const toInput = (value: number | string | null | undefined): string =>
        value !== null && value !== undefined && value !== ''
            ? String(value)
            : '';

    const { data, setData, put, transform, processing, errors } =
        useForm<ProductForm>({
            name: product.name,
            description: product.description ?? '',
            category_id: product.category_id ? String(product.category_id) : '',
            unit_of_measure_id: product.unit_of_measure_id
                ? String(product.unit_of_measure_id)
                : '',
            cif_percentage: product.cif_percentage ?? '0',
            price_threshold: product.price_threshold ?? '0',
            quality_viscosity_lower: toInput(product.quality_viscosity_lower),
            quality_viscosity_upper: toInput(product.quality_viscosity_upper),
            quality_fineness_lower: toInput(product.quality_fineness_lower),
            quality_fineness_upper: toInput(product.quality_fineness_upper),
            quality_solids_lower: toInput(product.quality_solids_lower),
            quality_solids_upper: toInput(product.quality_solids_upper),
            is_active: product.is_active,
        });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        // Sin costs.view no llegan CIF ni umbral: no se envían y el servidor conserva los guardados.
        transform((rawForm) => {
            const form = withDecimalPayloads(rawForm, PRODUCT_DECIMAL_FIELDS);

            if (can.viewCosts) {
                return form;
            }

            // eslint-disable-next-line @typescript-eslint/no-unused-vars
            const { cif_percentage, price_threshold, ...rest } = form;

            return rest;
        });
        put(productsShow({ product: product.id }).url);
    };

    return (
        <>
            <Head title={`Editar ${product.name}`} />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={productsIndex().url}
                            className="hover:text-foreground"
                        >
                            Productos
                        </Link>
                        <span>/</span>
                        <Link
                            href={productsShow({ product: product.id }).url}
                            className="hover:text-foreground"
                        >
                            {product.name}
                        </Link>
                        <span>/</span>
                        <span>Editar</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Editar Producto: {product.name}
                    </h1>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    {/* Identificación */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">
                            Identificación
                        </h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    maxLength={150}
                                    className="uppercase"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoría *</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(v) =>
                                        setData('category_id', v)
                                    }
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecciona categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem
                                                key={c.id}
                                                value={String(c.id)}
                                            >
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.category_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="unit_of_measure_id">
                                    Unidad de Medida *
                                </Label>
                                <Select
                                    value={data.unit_of_measure_id}
                                    onValueChange={(v) =>
                                        setData('unit_of_measure_id', v)
                                    }
                                >
                                    <SelectTrigger id="unit_of_measure_id">
                                        <SelectValue placeholder="Selecciona unidad" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {units.map((u) => (
                                            <SelectItem
                                                key={u.id}
                                                value={String(u.id)}
                                            >
                                                {u.name} ({u.symbol})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.unit_of_measure_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.unit_of_measure_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Textarea
                                id="description"
                                rows={4}
                                value={data.description}
                                onChange={(e) =>
                                    setData('description', e.target.value)
                                }
                                className="min-h-[100px] resize-y"
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">
                                    {errors.description}
                                </p>
                            )}
                        </div>

                        {can.deactivate && (
                            <div className="flex items-center gap-3">
                                <Checkbox
                                    id="is_active"
                                    checked={data.is_active}
                                    onCheckedChange={(checked) =>
                                        setData('is_active', checked === true)
                                    }
                                />
                                <Label htmlFor="is_active">
                                    Producto activo
                                </Label>
                            </div>
                        )}
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">
                            Rangos para certificado de calidad
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Límites de referencia (KU, HG, %) comparados con el
                            resultado al cerrar la orden.
                        </p>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Viscosidad (KU)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_viscosity_lower"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Mínimo
                                        </Label>
                                        <DecimalInput
                                            id="quality_viscosity_lower"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_viscosity_lower}
                                            value={data.quality_viscosity_lower}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_viscosity_lower',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_viscosity_lower && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_viscosity_lower}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_viscosity_upper"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Máximo
                                        </Label>
                                        <DecimalInput
                                            id="quality_viscosity_upper"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_viscosity_upper}
                                            value={data.quality_viscosity_upper}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_viscosity_upper',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_viscosity_upper && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_viscosity_upper}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Molienda (HG)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_fineness_lower"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Mínimo
                                        </Label>
                                        <DecimalInput
                                            id="quality_fineness_lower"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_fineness_lower}
                                            value={data.quality_fineness_lower}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_fineness_lower',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_fineness_lower && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_fineness_lower}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_fineness_upper"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Máximo
                                        </Label>
                                        <DecimalInput
                                            id="quality_fineness_upper"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_fineness_upper}
                                            value={data.quality_fineness_upper}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_fineness_upper',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_fineness_upper && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_fineness_upper}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Sólidos (%)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_solids_lower"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Mínimo
                                        </Label>
                                        <DecimalInput
                                            id="quality_solids_lower"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_solids_lower}
                                            value={data.quality_solids_lower}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_solids_lower',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_solids_lower && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_solids_lower}
                                            </p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label
                                            htmlFor="quality_solids_upper"
                                            className="text-xs font-normal text-muted-foreground"
                                        >
                                            Máximo
                                        </Label>
                                        <DecimalInput
                                            id="quality_solids_upper"
                                            {...PRODUCT_DECIMAL_LIMITS.quality_solids_upper}
                                            value={data.quality_solids_upper}
                                            onValueChange={(value) =>
                                                setData(
                                                    'quality_solids_upper',
                                                    value,
                                                )
                                            }
                                        />
                                        {errors.quality_solids_upper && (
                                            <p className="text-xs text-destructive">
                                                {errors.quality_solids_upper}
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Precios */}
                    {can.managePrices && can.viewCosts && (
                        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                            <h2 className="font-medium text-foreground">
                                Precios y Costos
                            </h2>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="current_cost">
                                        Costo actual
                                    </Label>
                                    <Input
                                        id="current_cost"
                                        value={toInput(product.current_cost)}
                                        readOnly
                                        className="cursor-not-allowed bg-muted/50"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        {hasPriceReferenceFormula
                                            ? 'Calculado automáticamente por la fórmula de referencia de precio.'
                                            : 'Se calculará automáticamente cuando se registre la fórmula de producción.'}
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="current_price">
                                        Precio Interno
                                    </Label>
                                    <Input
                                        id="current_price"
                                        value={toInput(product.current_price)}
                                        readOnly
                                        className="cursor-not-allowed bg-muted/50"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Calculado automáticamente: Costo actual
                                        × (1 + CIF %).
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="cif_percentage">
                                        CIF (%) *
                                    </Label>
                                    <DecimalInput
                                        id="cif_percentage"
                                        {...PRODUCT_DECIMAL_LIMITS.cif_percentage}
                                        required
                                        value={data.cif_percentage}
                                        onValueChange={(value) =>
                                            setData('cif_percentage', value)
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="price_threshold">
                                        Umbral mínimo (%)
                                    </Label>
                                    <DecimalInput
                                        id="price_threshold"
                                        {...PRODUCT_DECIMAL_LIMITS.price_threshold}
                                        value={data.price_threshold}
                                        onValueChange={(value) =>
                                            setData('price_threshold', value)
                                        }
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando…' : 'Guardar Cambios'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link
                                href={productsShow({ product: product.id }).url}
                            >
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
