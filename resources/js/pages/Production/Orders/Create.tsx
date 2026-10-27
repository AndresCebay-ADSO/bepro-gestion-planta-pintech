import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';

import { store as productionOrderStore } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { DecimalInput } from '@/components/decimal-input';
import InputError from '@/components/input-error';
import { IntegerInput } from '@/components/integer-input';
import { PageHeader } from '@/components/page-header';
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
import { PRODUCTION_ORDER_QUANTITY_LIMITS } from '@/lib/number-types';
import { parseDecimal, toDecimalPayload } from '@/lib/numeric-input';
import { index as productionOrderIndex } from '@/routes/production-orders';
import type { VariantOption } from '@/types/production-orders';

type FormulaOption = {
    id: number;
    version: number;
    name: string | null;
    is_price_reference: boolean;
};
type WarehouseOption = { id: number; name: string };
type ProductOption = {
    id: number;
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
    const { plantToday } = usePage().props;
    const { data, setData, post, processing, errors, clearErrors, transform } =
        useForm({
            product_id: '',
            formula_id: '',
            quantity: '',
            warehouse_id: warehouses[0]?.id?.toString() || '',
            planned_date: '',
            notes: '',
            color: '',
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
        label: p.name,
    }));

    const variantOptions = availableVariants.map((v) => ({
        id: String(v.id),
        label: `${v.name} — ${v.presentation_label} (${v.presentation_value} gal)`,
    }));

    // Una presentación solo una vez por orden: cada fila ofrece las que no eligieron las demás.
    const variantOptionsForRow = (index: number) => {
        const takenByOtherRows = new Set(
            data.packaging
                .filter((_, i) => i !== index)
                .map((pack) => pack.product_variant_id),
        );

        return variantOptions.filter(
            (option) => !takenByOtherRows.has(option.id),
        );
    };

    const packagingRowError = (index: number, field: keyof PackagingRow) =>
        (errors as Record<string, string>)[`packaging.${index}.${field}`];

    // Los errores de las filas van por posición (`packaging.N.campo`): al quitar o cambiar una fila dejarían de
    // corresponder a la fila bajo la que se muestran.
    const clearPackagingErrors = () => {
        const packagingErrorKeys = Object.keys(errors).filter((key) =>
            key.startsWith('packaging'),
        ) as Parameters<typeof clearErrors>;

        if (packagingErrorKeys.length > 0) {
            clearErrors(...packagingErrorKeys);
        }
    };

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        transform((formData) => ({
            ...formData,
            quantity: toDecimalPayload(formData.quantity),
        }));
        post(productionOrderStore().url);
    };

    const addPackaging = () => {
        setData('packaging', [
            ...data.packaging,
            { product_variant_id: '', planned_units: '' },
        ]);
    };

    const removePackaging = (index: number) => {
        clearPackagingErrors();
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
        clearPackagingErrors();
        setData('packaging', updated);
    };

    const plannedQuantity = parseDecimal(data.quantity);

    // Calcular total de galones planificados
    const totalPlannedGallons = data.packaging.reduce((sum, pack) => {
        const variant = availableVariants.find(
            (v) => v.id === Number(pack.product_variant_id),
        );
        const units = parseDecimal(pack.planned_units) ?? 0;
        const value = variant?.presentation_value || 0;

        return sum + units * value;
    }, 0);
    // A la escala de la cantidad (4 decimales): la suma con float deja ruido (3 × 0,1 = 0,30000000000000004), y con
    // `===` se mostraba «Diferencia: 0.00» aunque el plan coincidiera.
    const plannedDifference =
        plannedQuantity === null
            ? null
            : Number((plannedQuantity - totalPlannedGallons).toFixed(4));
    const packagingMatches = plannedDifference === 0;

    return (
        <>
            <Head title="Nueva orden de producción" />
            <div className="space-y-6 p-6">
                <div className="space-y-4">
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Link
                            href={productionOrderIndex().url}
                            className="hover:text-foreground"
                        >
                            Órdenes de producción
                        </Link>
                        <span>/</span>
                        <span>Nueva</span>
                    </div>
                    <PageHeader
                        title="Nueva orden de producción"
                        description="Planifica una nueva producción definiendo producto, cantidad y plan de envasado."
                    />
                </div>

                <form onSubmit={handleSubmit} className="max-w-4xl space-y-6">
                    {/* Información General */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="text-lg font-semibold text-foreground">
                            Información general
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
                                <InputError message={errors.product_id} />
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
                                                    v{f.version}
                                                    {f.name && ` · ${f.name}`}
                                                    {f.is_price_reference &&
                                                        ' (referencia de precio)'}
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.formula_id} />
                            </div>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <div className="space-y-2">
                                <Label htmlFor="quantity">
                                    Cantidad a Producir (galones) *
                                </Label>
                                <DecimalInput
                                    id="quantity"
                                    {...PRODUCTION_ORDER_QUANTITY_LIMITS}
                                    value={data.quantity}
                                    onValueChange={(value) =>
                                        setData('quantity', value)
                                    }
                                    placeholder="Ej: 50"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Galones totales de producto a fabricar
                                </p>
                                <InputError message={errors.quantity} />
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
                                <InputError message={errors.warehouse_id} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="planned_date">
                                    Fecha Planificada *
                                </Label>
                                <Input
                                    id="planned_date"
                                    type="date"
                                    min={plantToday}
                                    value={data.planned_date}
                                    onChange={(e) =>
                                        setData('planned_date', e.target.value)
                                    }
                                />
                                <InputError message={errors.planned_date} />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="color">Color (opcional)</Label>
                            <Input
                                id="color"
                                value={data.color}
                                maxLength={100}
                                onChange={(e) =>
                                    setData('color', e.target.value)
                                }
                                placeholder="Ej.: RAL 3020"
                            />
                            <p className="text-xs text-muted-foreground">
                                El que pidió el cliente. Se añade al nombre del
                                producto en la orden, el certificado y el QR.
                            </p>
                            <InputError message={errors.color} />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notas / Observaciones</Label>
                            <Textarea
                                id="notes"
                                maxLength={2000}
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Instrucciones especiales para esta producción..."
                                className="min-h-[80px]"
                            />
                            <InputError message={errors.notes} />
                        </div>
                    </div>

                    {/* Plan de Envasado */}
                    <div className="rounded-lg border border-border bg-card">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <div>
                                <h2 className="text-lg font-semibold text-foreground">
                                    Plan de envasado
                                </h2>
                                <p className="mt-0.5 text-xs text-muted-foreground">
                                    Define qué presentaciones se producirán y en
                                    qué cantidad. Total planificado:{' '}
                                    <strong>
                                        {totalPlannedGallons.toFixed(2)} gal
                                    </strong>
                                    {plannedDifference !== null &&
                                        totalPlannedGallons > 0 && (
                                            <span
                                                className={
                                                    packagingMatches
                                                        ? 'text-green-600'
                                                        : 'text-amber-600'
                                                }
                                            >
                                                {' '}
                                                {packagingMatches
                                                    ? '✓ Coincide con la cantidad'
                                                    : `⚠ Diferencia: ${plannedDifference} gal`}
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
                                    data.packaging.length >=
                                        availableVariants.length
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
                                                    Presentación
                                                </Label>
                                            )}
                                            <Combobox
                                                options={variantOptionsForRow(
                                                    index,
                                                )}
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
                                            <InputError
                                                message={packagingRowError(
                                                    index,
                                                    'product_variant_id',
                                                )}
                                            />
                                        </div>

                                        <div className="col-span-4 space-y-1">
                                            {index === 0 && (
                                                <Label className="text-xs text-muted-foreground">
                                                    Unidades a Producir
                                                </Label>
                                            )}
                                            <IntegerInput
                                                value={pack.planned_units}
                                                onValueChange={(value) =>
                                                    updatePackaging(
                                                        index,
                                                        'planned_units',
                                                        value,
                                                    )
                                                }
                                                placeholder="Ej: 10"
                                            />
                                            <InputError
                                                message={packagingRowError(
                                                    index,
                                                    'planned_units',
                                                )}
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

                        <InputError
                            className="px-6 pb-4"
                            message={
                                (errors as Record<string, string>).packaging
                            }
                        />
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
