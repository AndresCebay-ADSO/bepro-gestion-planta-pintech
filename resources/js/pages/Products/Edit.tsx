import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

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
import {
    show as productsShow,
    index as productsIndex,
} from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    product: {
        id: number;
        code: string;
        name: string;
        brand: string;
        description: string | null;
        category_id: number | null;
        unit_of_measure_id: number | null;
        current_cost: string | null;
        profit_margin: string | null;
        current_price: string | null;
        price_threshold: string | null;
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
    can: { managePrices: boolean };
};

type ProductForm = {
    code: string;
    name: string;
    brand: string;
    description: string;
    category_id: string;
    unit_of_measure_id: string;
    current_cost: string;
    profit_margin: string;
    current_price: string;
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
}: Props) {
    const toInput = (value: number | string | null | undefined): string =>
        value !== null && value !== undefined && value !== '' ? String(value) : '';

    const { data, setData, put, processing, errors } = useForm<ProductForm>({
        code: product.code,
        name: product.name,
        brand: product.brand ?? 'BEPRO',
        description: product.description ?? '',
        category_id: product.category_id ? String(product.category_id) : '',
        unit_of_measure_id: product.unit_of_measure_id
            ? String(product.unit_of_measure_id)
            : '',
        current_cost: product.current_cost ?? '',
        profit_margin: product.profit_margin ?? '0',
        current_price: product.current_price ?? '',
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
        put(productsShow({ product: product.id }).url);
    };

    return (
        <>
            <Head title={`Editar ${product.code}`} />
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
                            className="font-mono hover:text-foreground"
                        >
                            {product.code}
                        </Link>
                        <span>/</span>
                        <span>Editar</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Editar Producto:{' '}
                        <span className="font-mono">{product.code}</span>
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
                                <Label htmlFor="code">Código *</Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) =>
                                        setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    className="font-mono"
                                />
                                {errors.code && (
                                    <p className="text-sm text-destructive">
                                        {errors.code}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
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
                            <Label htmlFor="brand">Marca comercial *</Label>
                            <Input
                                id="brand"
                                value={data.brand}
                                onChange={(e) => setData('brand', e.target.value)}
                                maxLength={100}
                            />
                            {errors.brand && (
                                <p className="text-sm text-destructive">{errors.brand}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="description">Descripción</Label>
                            <Textarea
                                id="description"
                                rows={4}
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                className="min-h-[100px] resize-y"
                            />
                            {errors.description && (
                                <p className="text-sm text-destructive">{errors.description}</p>
                            )}
                        </div>

                        <div className="flex items-center gap-3">
                            <input
                                id="is_active"
                                type="checkbox"
                                checked={data.is_active}
                                onChange={(e) =>
                                    setData('is_active', e.target.checked)
                                }
                                className="h-4 w-4 rounded border-input"
                            />
                            <Label htmlFor="is_active">Producto activo</Label>
                        </div>
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">Rangos para certificado de calidad</h2>
                        <p className="text-xs text-muted-foreground">
                            Límites de referencia (KU, HG, %) comparados con el resultado al cerrar la orden.
                        </p>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <Label>Viscosidad (KU)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_viscosity_lower" className="text-xs font-normal text-muted-foreground">
                                            Mínimo
                                        </Label>
                                        <Input
                                            id="quality_viscosity_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_viscosity_lower}
                                            onChange={(e) => setData('quality_viscosity_lower', e.target.value)}
                                        />
                                        {errors.quality_viscosity_lower && (
                                            <p className="text-xs text-destructive">{errors.quality_viscosity_lower}</p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_viscosity_upper" className="text-xs font-normal text-muted-foreground">
                                            Máximo
                                        </Label>
                                        <Input
                                            id="quality_viscosity_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_viscosity_upper}
                                            onChange={(e) => setData('quality_viscosity_upper', e.target.value)}
                                        />
                                        {errors.quality_viscosity_upper && (
                                            <p className="text-xs text-destructive">{errors.quality_viscosity_upper}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Molienda (HG)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_fineness_lower" className="text-xs font-normal text-muted-foreground">
                                            Mínimo
                                        </Label>
                                        <Input
                                            id="quality_fineness_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_fineness_lower}
                                            onChange={(e) => setData('quality_fineness_lower', e.target.value)}
                                        />
                                        {errors.quality_fineness_lower && (
                                            <p className="text-xs text-destructive">{errors.quality_fineness_lower}</p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_fineness_upper" className="text-xs font-normal text-muted-foreground">
                                            Máximo
                                        </Label>
                                        <Input
                                            id="quality_fineness_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_fineness_upper}
                                            onChange={(e) => setData('quality_fineness_upper', e.target.value)}
                                        />
                                        {errors.quality_fineness_upper && (
                                            <p className="text-xs text-destructive">{errors.quality_fineness_upper}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-2">
                                <Label>Sólidos (%)</Label>
                                <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_solids_lower" className="text-xs font-normal text-muted-foreground">
                                            Mínimo
                                        </Label>
                                        <Input
                                            id="quality_solids_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value={data.quality_solids_lower}
                                            onChange={(e) => setData('quality_solids_lower', e.target.value)}
                                        />
                                        {errors.quality_solids_lower && (
                                            <p className="text-xs text-destructive">{errors.quality_solids_lower}</p>
                                        )}
                                    </div>
                                    <div className="space-y-1.5">
                                        <Label htmlFor="quality_solids_upper" className="text-xs font-normal text-muted-foreground">
                                            Máximo
                                        </Label>
                                        <Input
                                            id="quality_solids_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value={data.quality_solids_upper}
                                            onChange={(e) => setData('quality_solids_upper', e.target.value)}
                                        />
                                        {errors.quality_solids_upper && (
                                            <p className="text-xs text-destructive">{errors.quality_solids_upper}</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Precios */}
                    {can.managePrices && (
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
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        value={data.current_cost}
                                        onChange={(e) =>
                                            setData(
                                                'current_cost',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="current_price">
                                        Precio de venta
                                    </Label>
                                    <Input
                                        id="current_price"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        value={data.current_price}
                                        onChange={(e) =>
                                            setData(
                                                'current_price',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="profit_margin">
                                        Margen de ganancia (%) *
                                    </Label>
                                    <Input
                                        id="profit_margin"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        required
                                        value={data.profit_margin}
                                        onChange={(e) =>
                                            setData(
                                                'profit_margin',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="price_threshold">
                                        Umbral mínimo (%)
                                    </Label>
                                    <Input
                                        id="price_threshold"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.price_threshold}
                                        onChange={(e) =>
                                            setData(
                                                'price_threshold',
                                                e.target.value,
                                            )
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
