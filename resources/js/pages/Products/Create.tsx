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
import { index as productsIndex } from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
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
    cif_percentage: string;
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

export default function ProductsCreate({ categories, units, can }: Props) {
    const { data, setData, post, processing, errors } = useForm<ProductForm>({
        code: '',
        name: '',
        brand: 'BEPRO',
        description: '',
        category_id: '',
        unit_of_measure_id: '',
        current_cost: '',
        cif_percentage: '0',
        current_price: '',
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
        post(productsIndex().url);
    };

    return (
        <>
            <Head title="Crear Producto" />
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
                        <span>Crear</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Nuevo Producto
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Registra un nuevo producto de pintura en el catálogo.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    {/* Identificación */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">
                            Identificación
                        </h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="code">
                                    Referencia / Código interno
                                </Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) =>
                                        setData(
                                            'code',
                                            e.target.value.toUpperCase(),
                                        )
                                    }
                                    placeholder="Opcional..."
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
                            <h2 className="font-medium text-foreground">
                                Marca y descripción
                            </h2>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Marca comercial del catálogo. La descripción
                                puede mostrarse en la ficha pública del QR.
                            </p>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="brand">Marca comercial *</Label>
                            <Input
                                id="brand"
                                value={data.brand}
                                onChange={(e) =>
                                    setData('brand', e.target.value)
                                }
                                placeholder="Ej: BEPRO"
                                maxLength={100}
                            />
                            {errors.brand && (
                                <p className="text-sm text-destructive">
                                    {errors.brand}
                                </p>
                            )}
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
                            <h2 className="font-medium text-foreground">
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
                                        <Input
                                            id="quality_viscosity_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_viscosity_lower}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_viscosity_lower',
                                                    e.target.value,
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
                                        <Input
                                            id="quality_viscosity_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_viscosity_upper}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_viscosity_upper',
                                                    e.target.value,
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
                                        <Input
                                            id="quality_fineness_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_fineness_lower}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_fineness_lower',
                                                    e.target.value,
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
                                        <Input
                                            id="quality_fineness_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={data.quality_fineness_upper}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_fineness_upper',
                                                    e.target.value,
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
                                        <Input
                                            id="quality_solids_lower"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value={data.quality_solids_lower}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_solids_lower',
                                                    e.target.value,
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
                                        <Input
                                            id="quality_solids_upper"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            max="100"
                                            value={data.quality_solids_upper}
                                            onChange={(e) =>
                                                setData(
                                                    'quality_solids_upper',
                                                    e.target.value,
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
                                <h2 className="font-medium text-foreground">
                                    Precios y Costos
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
                                        placeholder="0.0000"
                                    />
                                    {errors.current_cost && (
                                        <p className="text-sm text-destructive">
                                            {errors.current_cost}
                                        </p>
                                    )}
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
                                        placeholder="0.0000"
                                    />
                                    {errors.current_price && (
                                        <p className="text-sm text-destructive">
                                            {errors.current_price}
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="cif_percentage">
                                        CIF (%) *
                                    </Label>
                                    <Input
                                        id="cif_percentage"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        required
                                        value={data.cif_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'cif_percentage',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="0.00"
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
