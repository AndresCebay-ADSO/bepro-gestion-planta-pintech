import { Head, Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

import { store as productionOrderStore } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
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
import { index as productionOrderIndex } from '@/routes/production-orders';

type FormulaOption = { id: number; version: number; is_active: boolean };
type VariantOption = {
    id: number;
    sku: string;
    presentation_label: string;
    presentation_value: number;
};
type WarehouseOption = { id: number; name: string };
type ProductOption = {
    id: number;
    code: string;
    name: string;
    formulas?: FormulaOption[];
    variants?: VariantOption[];
};

type PackagingRow = {
    product_variant_id: string;
    planned_units: string;
};

type Props = {
    products: ProductOption[];
    warehouses: WarehouseOption[];
};

export default function ProductionOrdersCreate({
    products,
    warehouses,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        product_id: '',
        formula_id: '',
        quantity: '',
        warehouse_id: warehouses[0]?.id?.toString() || '',
        planned_date: '',
        notes: '',
        packaging: [] as PackagingRow[],
    });

    // Cuando cambia el producto, actualizamos las fórmulas disponibles
    const selectedProduct = products.find(
        (p) => p.id === Number(data.product_id),
    );
    const availableFormulas = selectedProduct?.formulas || [];
    const availableVariants = selectedProduct?.variants || [];

    const productOptions = products.map((p) => ({
        id: String(p.id),
        label: `${p.code} — ${p.name}`,
    }));

    const variantOptions = availableVariants.map((v) => ({
        id: String(v.id),
        label: `${v.sku} — ${v.presentation_label} (${v.presentation_value} gal)`,
    }));

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(productionOrderStore().url);
    };

    const addPackaging = () => {
        setData('packaging', [
            ...data.packaging,
            { product_variant_id: '', planned_units: '' },
        ]);
    };

    const removePackaging = (index: number) => {
        setData(
            'packaging',
            data.packaging.filter((_, i) => i !== index),
        );
    };

    const updatePackaging = (
        index: number,
        field: keyof PackagingRow,
        value: string,
    ) => {
        const updated = data.packaging.map((pack, i) =>
            i === index ? { ...pack, [field]: value } : pack,
        );
        setData('packaging', updated);
    };

    // Calcular total de galones planificados
    const totalPlannedGallons = data.packaging.reduce((sum, pack) => {
        const variant = availableVariants.find(
            (v) => v.id === Number(pack.product_variant_id),
        );
        const units = parseFloat(pack.planned_units) || 0;
        const value = variant?.presentation_value || 0;

        return sum + units * value;
    }, 0);

    return (
        <>
            <Head title="Nueva Orden de Producción" />
            <div className="space-y-6 p-6">
                <div className="flex flex-col gap-2">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={productionOrderIndex().url}
                            className="hover:text-foreground"
                        >
                            Órdenes de Producción
                        </Link>
                        <span>/</span>
                        <span>Nueva</span>
                    </div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        Nueva Orden de Producción
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Planifica una nueva producción definiendo producto,
                        cantidad y plan de envasado.
                    </p>
                </div>

                <form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
                    {/* Información General */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="font-medium text-foreground">
                            Información General
                        </h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="product_id">Producto *</Label>
                                <Combobox
                                    options={productOptions}
                                    value={data.product_id}
                                    onChange={(v) => {
                                        setData({
                                            ...data,
                                            product_id: String(v),
                                            formula_id: '',
                                            packaging: [],
                                        });
                                    }}
                                    placeholder="Busca o selecciona un producto..."
                                />
                                {errors.product_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.product_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="formula_id">Fórmula *</Label>
                                <Select
                                    value={data.formula_id}
                                    onValueChange={(v) =>
                                        setData('formula_id', v)
                                    }
                                    disabled={
                                        !data.product_id ||
                                        availableFormulas.length === 0
                                    }
                                >
                                    <SelectTrigger id="formula_id">
                                        <SelectValue
                                            placeholder={
                                                !data.product_id
                                                    ? 'Selecciona un producto primero'
                                                    : availableFormulas.length ===
                                                        0
                                                      ? 'No hay fórmulas activas'
                                                      : 'Selecciona fórmula'
                                            }
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {availableFormulas.map(
                                            (f: FormulaOption) => (
                                                <SelectItem
                                                    key={f.id}
                                                    value={String(f.id)}
                                                >
                                                    Versión {f.version}
                                                    {f.is_active && ' (Activa)'}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {errors.formula_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.formula_id}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="quantity">
                                    Cantidad a Producir (galones) *
                                </Label>
                                <Input
                                    id="quantity"
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    value={data.quantity}
                                    onChange={(e) =>
                                        setData('quantity', e.target.value)
                                    }
                                    placeholder="Ej: 50"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Galones totales de producto a fabricar
                                </p>
                                {errors.quantity && (
                                    <p className="text-sm text-destructive">
                                        {errors.quantity}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="warehouse_id">Bodega *</Label>
                                <Select
                                    value={data.warehouse_id}
                                    onValueChange={(v) =>
                                        setData('warehouse_id', v)
                                    }
                                >
                                    <SelectTrigger id="warehouse_id">
                                        <SelectValue placeholder="Selecciona bodega" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {warehouses.map((w) => (
                                            <SelectItem
                                                key={w.id}
                                                value={String(w.id)}
                                            >
                                                {w.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.warehouse_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.warehouse_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="planned_date">
                                    Fecha Planificada *
                                </Label>
                                <Input
                                    id="planned_date"
                                    type="date"
                                    value={data.planned_date}
                                    onChange={(e) =>
                                        setData('planned_date', e.target.value)
                                    }
                                />
                                {errors.planned_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.planned_date}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notas / Observaciones</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Instrucciones especiales para esta producción..."
                                className="min-h-[80px]"
                            />
                            {errors.notes && (
                                <p className="text-sm text-destructive">
                                    {errors.notes}
                                </p>
                            )}
                        </div>
                    </div>

                    {/* Plan de Envasado */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <div>
                                <h2 className="font-medium text-foreground">
                                    Plan de Envasado
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Define qué presentaciones se producirán y en
                                    qué cantidad. Total planificado:{' '}
                                    <strong>
                                        {totalPlannedGallons.toFixed(2)} gal
                                    </strong>
                                    {data.quantity &&
                                        totalPlannedGallons > 0 && (
                                            <span
                                                className={
                                                    totalPlannedGallons ===
                                                    parseFloat(data.quantity)
                                                        ? 'text-green-600'
                                                        : 'text-amber-600'
                                                }
                                            >
                                                {' '}
                                                {totalPlannedGallons ===
                                                parseFloat(data.quantity)
                                                    ? '✓ Coincide con la cantidad'
                                                    : `⚠ Diferencia: ${(parseFloat(data.quantity) - totalPlannedGallons).toFixed(2)} gal`}
                                            </span>
                                        )}
                                </p>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addPackaging}
                                disabled={
                                    !data.product_id ||
                                    availableVariants.length === 0
                                }
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Agregar Presentación
                            </Button>
                        </div>

                        <div className="divide-y divide-border">
                            {data.packaging.length === 0 ? (
                                <div className="p-8 text-center text-sm text-muted-foreground">
                                    {!data.product_id
                                        ? 'Selecciona un producto para ver las presentaciones disponibles'
                                        : availableVariants.length === 0
                                          ? 'Este producto no tiene variantes definidas'
                                          : "Haz clic en 'Agregar Presentación' para definir el plan de envasado"}
                                </div>
                            ) : (
                                data.packaging.map((pack, index) => (
                                    <div
                                        key={index}
                                        className="grid grid-cols-12 items-end gap-3 p-4"
                                    >
                                        <div className="col-span-6 space-y-1">
                                            {index === 0 && (
                                                <Label className="text-xs text-muted-foreground">
                                                    Presentación (SKU)
                                                </Label>
                                            )}
                                            <Combobox
                                                options={variantOptions}
                                                value={pack.product_variant_id}
                                                onChange={(v) =>
                                                    updatePackaging(
                                                        index,
                                                        'product_variant_id',
                                                        String(v),
                                                    )
                                                }
                                                placeholder="Presentación..."
                                                disabled={!data.product_id}
                                            />
                                        </div>

                                        <div className="col-span-4 space-y-1">
                                            {index === 0 && (
                                                <Label className="text-xs text-muted-foreground">
                                                    Unidades a Producir
                                                </Label>
                                            )}
                                            <Input
                                                type="number"
                                                step="1"
                                                min="0"
                                                value={pack.planned_units}
                                                onChange={(e) =>
                                                    updatePackaging(
                                                        index,
                                                        'planned_units',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Ej: 10"
                                            />
                                        </div>

                                        <div className="col-span-2 flex items-center justify-end">
                                            {data.packaging.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        removePackaging(index)
                                                    }
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                        </div>

                        {(errors as Record<string, string>).packaging && (
                            <p className="px-6 pb-4 text-sm text-destructive">
                                {(errors as Record<string, string>).packaging}
                            </p>
                        )}
                    </div>

                    <div className="flex gap-3">
                        <Button type="submit" disabled={processing}>
                            {processing
                                ? 'Creando…'
                                : 'Crear Orden de Producción'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={productionOrderIndex().url}>
                                Cancelar
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
