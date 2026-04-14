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
import { show as productsShow, index as productsIndex } from '@/routes/products';

type Option = { id: number; name: string; symbol?: string };

type Props = {
    categories: Option[];
    units: Option[];
    can: { managePrices: boolean };
};

type ProductForm = {
    code: string;
    name: string;
    category_id: string;
    unit_of_measure_id: string;
    current_cost: string;
    profit_margin: string;
    current_price: string;
    price_threshold: string;
    is_active: boolean;
};

export default function ProductsCreate({ categories, units, can }: Props) {
    const { data, setData, post, processing, errors } = useForm<ProductForm>({
        code: '',
        name: '',
        category_id: '',
        unit_of_measure_id: '',
        current_cost: '',
        profit_margin: '',
        current_price: '',
        price_threshold: '0',
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
                        <Link href={productsIndex().url} className="hover:text-foreground">
                            Productos
                        </Link>
                        <span>/</span>
                        <span>Crear</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">Nuevo Producto</h1>
                    <p className="text-sm text-muted-foreground">
                        Registra un nuevo producto de pintura en el catálogo.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl space-y-6">
                    {/* Identificación */}
                    <div className="rounded-lg border border-border bg-card p-6 space-y-4">
                        <h2 className="font-medium text-foreground">Identificación</h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="code">Referencia / Código interno</Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={(e) => setData('code', e.target.value.toUpperCase())}
                                    placeholder="Opcional..."
                                    className="font-mono"
                                />
                                {errors.code && (
                                    <p className="text-sm text-destructive">{errors.code}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">Nombre *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Nombre descriptivo del producto"
                                />
                                {errors.name && (
                                    <p className="text-sm text-destructive">{errors.name}</p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="category_id">Categoría *</Label>
                                <Select
                                    value={data.category_id}
                                    onValueChange={(v) => setData('category_id', v)}
                                >
                                    <SelectTrigger id="category_id">
                                        <SelectValue placeholder="Selecciona categoría" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {categories.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.category_id && (
                                    <p className="text-sm text-destructive">{errors.category_id}</p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="unit_of_measure_id">Unidad de Medida *</Label>
                                <Select
                                    value={data.unit_of_measure_id}
                                    onValueChange={(v) => setData('unit_of_measure_id', v)}
                                >
                                    <SelectTrigger id="unit_of_measure_id">
                                        <SelectValue placeholder="Selecciona unidad" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {units.map((u) => (
                                            <SelectItem key={u.id} value={String(u.id)}>
                                                {u.name} ({u.symbol})
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.unit_of_measure_id && (
                                    <p className="text-sm text-destructive">{errors.unit_of_measure_id}</p>
                                )}
                            </div>
                        </div>
                    </div>

                    {/* Precios (solo si tiene permiso) */}
                    {can.managePrices && (
                        <div className="rounded-lg border border-border bg-card p-6 space-y-4">
                            <div>
                                <h2 className="font-medium text-foreground">Precios y Costos</h2>
                                <p className="text-xs text-muted-foreground mt-1">Campos opcionales. Puedes completarlos más adelante.</p>
                            </div>

                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="current_cost">Costo actual</Label>
                                    <Input
                                        id="current_cost"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        value={data.current_cost}
                                        onChange={(e) => setData('current_cost', e.target.value)}
                                        placeholder="0.0000"
                                    />
                                    {errors.current_cost && (
                                        <p className="text-sm text-destructive">{errors.current_cost}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="current_price">Precio de venta</Label>
                                    <Input
                                        id="current_price"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        value={data.current_price}
                                        onChange={(e) => setData('current_price', e.target.value)}
                                        placeholder="0.0000"
                                    />
                                    {errors.current_price && (
                                        <p className="text-sm text-destructive">{errors.current_price}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="profit_margin">Margen de ganancia (%)</Label>
                                    <Input
                                        id="profit_margin"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.profit_margin}
                                        onChange={(e) => setData('profit_margin', e.target.value)}
                                        placeholder="0.00"
                                    />
                                    {errors.profit_margin && (
                                        <p className="text-sm text-destructive">{errors.profit_margin}</p>
                                    )}
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="price_threshold">Umbral mínimo de precio (%)</Label>
                                    <Input
                                        id="price_threshold"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="100"
                                        value={data.price_threshold}
                                        onChange={(e) => setData('price_threshold', e.target.value)}
                                    />
                                    {errors.price_threshold && (
                                        <p className="text-sm text-destructive">{errors.price_threshold}</p>
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
