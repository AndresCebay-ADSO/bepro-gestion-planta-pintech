import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { PageHeader } from '@/components/page-header';
import { Button } from '@/components/ui/button';
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
import { index as productsIndex } from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean };
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

export default function ProductsCreate({ categories, units, can }: Props) {
    const { data, setData, post, transform, processing, errors } =
        useForm<ProductForm>({
            name: '',
            description: '',
            category_id: '',
            unit_of_measure_id: '',
            cif_percentage: '0',
            price_threshold: '0',
            quality_viscosity_lower: '',
            quality_viscosity_upper: '',
            quality_fineness_lower: '',
            quality_fineness_upper: '',
            quality_solids_lower: '',
            quality_solids_upper: '',
            is_active: true,
        });

    const handleSubmit = (e: FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        transform((form) => withDecimalPayloads(form, PRODUCT_DECIMAL_FIELDS));
        post(productsIndex().url);
    };

    return (
        <>
            <Head title="Nuevo producto" />
            <div className="space-y-6 p-6">
                <div className="space-y-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={productsIndex().url}
                            className="hover:text-foreground"
                        >
                            Productos
                        </Link>
                        <span>/</span>
                        <span>Crear</span>
                    </div>
                    <PageHeader
                        title="Nuevo producto"
                        description="Registra un nuevo producto de pintura en el catálogo."
                    />
                </div>

                <form
                    noValidate
                    onSubmit={handleSubmit}
                    className="max-w-2xl space-y-6"
                >
                    {/* Identificación */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="text-lg font-semibold text-foreground">
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
                                    className="uppercase placeholder:normal-case"
                                    placeholder="Nombre descriptivo del producto"
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
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Descripción
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                La descripción puede mostrarse en la ficha
                                pública del QR.
                            </p>
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
                                placeholder="Resumen del producto, usos recomendados, notas para comercial o calidad…"
                                className="min-h-[100px] resize-y"
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">
                                    {errors.description}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <div>
                            <h2 className="text-lg font-semibold text-foreground">
                                Rangos para certificado de calidad
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Límites de referencia comparados con viscosidad
                                (KU), molienda (HG) y sólidos (%) al cerrar la
                                orden. Dejar vacío si no aplica.
                            </p>
                        </div>
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
                                            placeholder="Ej: 90"
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
                                            placeholder="Ej: 110"
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

                    {/* Precios (solo si tiene permiso) */}
                    {can.managePrices && (
                        <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                            <div>
                                <h2 className="text-lg font-semibold text-foreground">
                                    Precios y costos
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Define CIF y umbral para cálculo automático
                                    de precios.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="current_cost">
                                        Costo actual
                                    </Label>
                                    <Input
                                        id="current_cost"
                                        value=""
                                        readOnly
                                        className="cursor-not-allowed bg-muted/50"
                                        placeholder="0"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Se calculará automáticamente cuando se
                                        registre la fórmula de producción.
                                    </p>
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="current_price">
                                        Precio Interno
                                    </Label>
                                    <Input
                                        id="current_price"
                                        value=""
                                        readOnly
                                        className="cursor-not-allowed bg-muted/50"
                                        placeholder="0"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Se calculará automáticamente: Costo
                                        actual × (1 + CIF %).
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
                                        placeholder="0"
                                    />
                                    {errors.cif_percentage && (
                                        <p className="text-sm text-destructive">
                                            {errors.cif_percentage}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="price_threshold">
                                        Umbral mínimo de precio (%)
                                    </Label>
                                    <DecimalInput
                                        id="price_threshold"
                                        {...PRODUCT_DECIMAL_LIMITS.price_threshold}
                                        value={data.price_threshold}
                                        onValueChange={(value) =>
                                            setData('price_threshold', value)
                                        }
                                    />
                                    {errors.price_threshold && (
                                        <p className="text-sm text-destructive">
                                            {errors.price_threshold}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Guardando…' : 'Crear Producto'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={productsIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
