import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    create as formulasCreate,
    show as formulasShow,
} from '@/routes/formulas';
import { index as productsIndex } from '@/routes/products';

type FormulaItem = {
    id: number;
    version: number;
    is_active: boolean;
    notes: string | null;
    created_at: string;
    created_by?: { name: string } | null;
};

type Props = {
    product: {
        id: number;
        code: string;
        name: string;
        is_active: boolean;
        category?: { name: string } | null;
        unit_of_measure?: { name: string; symbol: string } | null;
        current_cost?: string | null;
        current_price?: string | null;
        profit_margin?: string | null;
        variants?: Array<{
            id: number;
            sku: string;
            presentation_label?: string | null;
            color?: string | null;
            finish?: string | null;
            base_type?: string | null;
            component_system: '1K' | '2K' | 'KIT';
            current_price?: string | null;
            is_active: boolean;
            unit_of_measure?: { name: string; symbol: string } | null;
        }>;
        formulas?: FormulaItem[];
    };
    can: {
        update: boolean;
        delete: boolean;
    };
    units: Array<{
        id: number;
        name: string;
        symbol: string;
    }>;
    rawMaterials?: Array<{
        id: number;
        code: string;
        category?: { id: number; name: string };
    }>;
};

export default function ProductsShow({ product, can, units, rawMaterials }: Props) {
    const [isOpen, setIsOpen] = useState(false);

    const form = useForm({
        sku: '',
        unit_of_measure_id: '',
        presentation_value: '',
        presentation_label: '',
        color: '',
        finish: '',
        base_type: '',
        component_system: '1K',
        current_cost: '',
        current_price: '',
        package_raw_material_id: '',
        is_active: true,
    });

    const handleDelete = () => {
        if (!window.confirm(`¿Eliminar el producto ${product.code}?`)) {
            return;
        }

        router.delete(`/products/${product.id}`);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(`/products/${product.id}/variants`, {
            onSuccess: () => {
                setIsOpen(false);
                form.reset();
            },
        });
    };

    const activeFormula = product.formulas?.find((f) => f.is_active);

    return (
        <>
            <Head title={`Producto ${product.code}`} />
            <div className="space-y-6 p-6">
                {/* Header */}
                <div className="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
                    <div className="space-y-1">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <Link
                                href={productsIndex().url}
                                className="hover:text-foreground"
                            >
                                Productos
                            </Link>
                            <span>/</span>
                            <span className="font-mono">{product.code}</span>
                        </div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-2xl font-semibold text-foreground">
                                {product.name}
                            </h1>
                            <Badge
                                variant={
                                    product.is_active ? 'default' : 'secondary'
                                }
                            >
                                {product.is_active ? 'Activo' : 'Inactivo'}
                            </Badge>
                        </div>
                        <p className="font-mono text-sm text-muted-foreground">
                            {product.code}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Button variant="outline" asChild>
                            <Link href={productsIndex().url}>Volver</Link>
                        </Button>
                        {can.update && (
                            <Button variant="outline" asChild>
                                <Link href={`/products/${product.id}/edit`}>
                                    Editar
                                </Link>
                            </Button>
                        )}
                        {can.delete && (
                            <Button
                                variant="destructive"
                                onClick={handleDelete}
                            >
                                Eliminar
                            </Button>
                        )}
                    </div>
                </div>

                {/* Info del producto */}
                <div className="grid gap-4 rounded-lg border border-border bg-card p-6 md:grid-cols-3">
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Categoría
                        </p>
                        <p className="text-sm text-foreground">
                            {product.category?.name ?? '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Unidad de Medida
                        </p>
                        <p className="text-sm text-foreground">
                            {product.unit_of_measure
                                ? `${product.unit_of_measure.name} (${product.unit_of_measure.symbol})`
                                : '-'}
                        </p>
                    </div>
                    <div>
                        <p className="text-xs tracking-wide text-muted-foreground uppercase">
                            Precio de Venta
                        </p>
                        <p className="text-sm font-medium text-foreground">
                            {product.current_price
                                ? <FormattedNumber value={product.current_price} currency maxDecimals={2} trimTrailingZeros />
                                : 'No asignado'}
                        </p>
                    </div>
                </div>

                {/* Variantes */}
                <div className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                            <h2 className="font-medium text-foreground">
                                Variantes / SKU
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                Presentaciones de venta: galón, bidón, tambor, etc. El valor se define en galones.
                            </p>
                        </div>
                        {can.update && (
                            <Dialog open={isOpen} onOpenChange={setIsOpen}>
                                <DialogTrigger asChild>
                                    <Button size="sm">Nueva Variante</Button>
                                </DialogTrigger>
                                <DialogContent className="max-w-lg">
                                    <DialogHeader>
                                        <DialogTitle>Nueva Variante</DialogTitle>
                                    </DialogHeader>
                                    <form onSubmit={handleSubmit} className="space-y-4">
                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="sku">SKU</Label>
                                                <Input
                                                    id="sku"
                                                    value={form.data.sku}
                                                    onChange={(e) => form.setData('sku', e.target.value)}
                                                    placeholder="Ej: ESM-BLA-01-GL"
                                                />
                                                {form.errors.sku && (
                                                    <p className="text-xs text-destructive">{form.errors.sku}</p>
                                                )}
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="unit_of_measure_id">Unidad de Medida</Label>
                                                <Select
                                                    value={form.data.unit_of_measure_id}
                                                    onValueChange={(v) => form.setData('unit_of_measure_id', v)}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Selecciona..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {units.map((u) => (
                                                            <SelectItem key={u.id} value={String(u.id)}>
                                                                {u.name} ({u.symbol})
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                {form.errors.unit_of_measure_id && (
                                                    <p className="text-xs text-destructive">{form.errors.unit_of_measure_id}</p>
                                                )}
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="presentation_value">Valor Presentación (en galones)</Label>
                                                <Input
                                                    id="presentation_value"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.presentation_value}
                                                    onChange={(e) => form.setData('presentation_value', e.target.value)}
                                                    placeholder="Ej: 1, 5, 0.75, 50"
                                                />
                                                <p className="text-xs text-muted-foreground">
                                                    Ejemplos: 1 = Galón, 5 = Bidón 5gal, 0.75 = 3/4 galón, 50 = Tambor
                                                </p>
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="presentation_label">Label Presentación</Label>
                                                <Input
                                                    id="presentation_label"
                                                    value={form.data.presentation_label}
                                                    onChange={(e) => form.setData('presentation_label', e.target.value)}
                                                    placeholder="Ej: Galón 3.785L, Bidón 5 Gal"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="color">Color</Label>
                                                <Input
                                                    id="color"
                                                    value={form.data.color}
                                                    onChange={(e) => form.setData('color', e.target.value)}
                                                    placeholder="Ej: Blanco"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="finish">Acabado</Label>
                                                <Input
                                                    id="finish"
                                                    value={form.data.finish}
                                                    onChange={(e) => form.setData('finish', e.target.value)}
                                                    placeholder="Ej: Brillante"
                                                />
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="base_type">Tipo Base</Label>
                                                <Input
                                                    id="base_type"
                                                    value={form.data.base_type}
                                                    onChange={(e) => form.setData('base_type', e.target.value)}
                                                    placeholder="Ej: Agua / Solvente"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="component_system">Sistema</Label>
                                                <Select
                                                    value={form.data.component_system}
                                                    onValueChange={(v) => form.setData('component_system', v as '1K' | '2K' | 'KIT')}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="1K">1K (Un componente)</SelectItem>
                                                        <SelectItem value="2K">2K (Dos componentes)</SelectItem>
                                                        <SelectItem value="KIT">KIT (Kit completo)</SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>

                                        <div className="grid grid-cols-2 gap-4">
                                            <div className="space-y-2">
                                                <Label htmlFor="current_cost">Costo Actual</Label>
                                                <Input
                                                    id="current_cost"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.current_cost}
                                                    onChange={(e) => form.setData('current_cost', e.target.value)}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="current_price">Precio Venta</Label>
                                                <Input
                                                    id="current_price"
                                                    type="number"
                                                    step="0.0001"
                                                    value={form.data.current_price}
                                                    onChange={(e) => form.setData('current_price', e.target.value)}
                                                    placeholder="0.00"
                                                />
                                            </div>
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="package_raw_material_id">Envase / Contenedor</Label>
                                            <Select
                                                value={form.data.package_raw_material_id}
                                                onValueChange={(v) => form.setData('package_raw_material_id', v)}
                                            >
                                                <SelectTrigger id="package_raw_material_id">
                                                    <SelectValue placeholder="Selecciona el envase (opcional)" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {rawMaterials?.map((rm: any) => (
                                                        <SelectItem key={rm.id} value={String(rm.id)}>
                                                            {rm.code}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            <p className="text-xs text-muted-foreground">
                                                Se descontará del inventario al completar la orden de producción
                                            </p>
                                        </div>

                                        <div className="flex justify-end gap-2 pt-4">
                                            <Button type="button" variant="outline" onClick={() => setIsOpen(false)}>
                                                Cancelar
                                            </Button>
                                            <Button type="submit" disabled={form.processing}>
                                                {form.processing ? 'Guardando...' : 'Guardar Variante'}
                                            </Button>
                                        </div>
                                    </form>
                                </DialogContent>
                            </Dialog>
                        )}
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-4 text-left font-medium">SKU</th>
                                <th className="p-4 text-left font-medium">Presentación</th>
                                <th className="p-4 text-left font-medium">Color</th>
                                <th className="p-4 text-left font-medium">Acabado</th>
                                <th className="p-4 text-left font-medium">Sistema</th>
                                <th className="p-4 text-left font-medium">Precio</th>
                                <th className="p-4 text-left font-medium">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(product.variants ?? []).map((variant) => (
                                <tr
                                    key={variant.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">{variant.sku}</td>
                                    <td className="p-4 text-muted-foreground">
                                        {variant.presentation_label ?? '-'}
                                        {variant.unit_of_measure
                                            ? ` (${variant.unit_of_measure.symbol})`
                                            : ''}
                                    </td>
                                    <td className="p-4 text-muted-foreground">{variant.color ?? '-'}</td>
                                    <td className="p-4 text-muted-foreground">{variant.finish ?? '-'}</td>
                                    <td className="p-4">
                                        <Badge variant="secondary">{variant.component_system}</Badge>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {variant.current_price ? <FormattedNumber value={variant.current_price} currency maxDecimals={2} /> : '-'}
                                    </td>
                                    <td className="p-4">
                                        <Badge variant={variant.is_active ? 'default' : 'secondary'}>
                                            {variant.is_active ? 'Activa' : 'Inactiva'}
                                        </Badge>
                                    </td>
                                </tr>
                            ))}
                            {(product.variants ?? []).length === 0 && (
                                <tr>
                                    <td colSpan={7} className="p-8 text-center text-sm text-muted-foreground">
                                        Este producto aún no tiene variantes registradas.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                <div className="rounded-lg border border-border bg-card">
                    <div className="flex items-center justify-between border-b border-border px-6 py-4">
                        <div>
                            <h2 className="font-medium text-foreground">
                                Fórmulas
                            </h2>
                            <p className="mt-0.5 text-xs text-muted-foreground">
                                {activeFormula
                                    ? `Versión activa: v${activeFormula.version}`
                                    : 'Sin fórmula activa'}
                            </p>
                        </div>
                        <Button size="sm" asChild>
                            <Link
                                href={
                                    formulasCreate({ query: { product_id: product.id } })
                                        .url
                                }
                            >
                                Nueva Fórmula
                            </Link>
                        </Button>
                    </div>

                    <table className="w-full text-sm">
                        <thead className="border-b border-border bg-muted/40">
                            <tr>
                                <th className="p-4 text-left font-medium">
                                    Versión
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Estado
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Notas
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Creada por
                                </th>
                                <th className="p-4 text-left font-medium">
                                    Fecha
                                </th>
                                <th className="p-4 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {(product.formulas ?? []).map((formula) => (
                                <tr
                                    key={formula.id}
                                    className="border-b border-border/60 transition-colors last:border-0 hover:bg-muted/30"
                                >
                                    <td className="p-4 font-mono font-medium">
                                        v{formula.version}
                                    </td>
                                    <td className="p-4">
                                        <Badge
                                            variant={
                                                formula.is_active
                                                    ? 'default'
                                                    : 'secondary'
                                            }
                                        >
                                            {formula.is_active
                                                ? 'Activa'
                                                : 'Inactiva'}
                                        </Badge>
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.notes ?? '-'}
                                    </td>
                                    <td className="p-4 text-muted-foreground">
                                        {formula.created_by?.name ?? '-'}
                                    </td>
                                    <td className="p-4 text-xs text-muted-foreground">
                                        <FormattedDate value={formula.created_at} format="datetime" />
                                    </td>
                                    <td className="p-4 text-right">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            asChild
                                        >
                                            <Link
                                                href={
                                                    formulasShow({
                                                        formula: formula.id,
                                                    }).url
                                                }
                                            >
                                                Ver
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {(product.formulas ?? []).length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="p-8 text-center text-sm text-muted-foreground"
                                    >
                                        No hay fórmulas registradas. Crea la
                                        primera usando el botón "Nueva Fórmula".
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}
