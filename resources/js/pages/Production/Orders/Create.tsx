import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { store as productionOrderStore } from '@/actions/App/Http/Controllers/ProductionOrderController';
import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { FormLineItem } from '@/components/form-line-item';
import { FormPage } from '@/components/form-page';
import InputError from '@/components/input-error';
import { IntegerInput } from '@/components/integer-input';
import { PageHeader } from '@/components/page-header';
import { PlantDateInput } from '@/components/plant-date-input';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
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
import {
    create as productionOrderCreate,
    index as productionOrderIndex,
} from '@/routes/production-orders';
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
            <FormPage width="wide">
                <PageHeader
                    title="Nueva orden de producción"
                    description="Planifica una nueva producción definiendo producto, cantidad y plan de envasado."
                />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    {/* Información General */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="text-lg font-semibold text-foreground">
                            Información general
                        </h2>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <FormField
                                id="product_id"
                                label="Producto"
                                required
                                error={errors.product_id}
                            >
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
                            </FormField>

                            <FormField
                                id="formula_id"
                                label="Fórmula"
                                required
                                error={errors.formula_id}
                            >
                                {(control) => (
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
                                        <SelectTrigger {...control}>
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
                                                        {f.name &&
                                                            ` · ${f.name}`}
                                                        {f.is_price_reference &&
                                                            ' (referencia de precio)'}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                            <FormField
                                id="quantity"
                                label="Cantidad a Producir (galones)"
                                required
                                error={errors.quantity}
                                help="Galones totales de producto a fabricar"
                            >
                                <DecimalInput
                                    {...PRODUCTION_ORDER_QUANTITY_LIMITS}
                                    value={data.quantity}
                                    onValueChange={(value) =>
                                        setData('quantity', value)
                                    }
                                    placeholder="Ej: 50"
                                />
                            </FormField>

                            <FormField
                                id="warehouse_id"
                                label="Bodega"
                                required
                                error={errors.warehouse_id}
                            >
                                {(control) => (
                                    <Select
                                        value={data.warehouse_id}
                                        onValueChange={(v) =>
                                            setData('warehouse_id', v)
                                        }
                                    >
                                        <SelectTrigger {...control}>
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
                                )}
                            </FormField>

                            <FormField
                                id="planned_date"
                                label="Fecha Planificada"
                                required
                                error={errors.planned_date}
                            >
                                <PlantDateInput
                                    notPast
                                    value={data.planned_date}
                                    onChange={(value) =>
                                        setData('planned_date', value)
                                    }
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="color"
                            label="Color (opcional)"
                            error={errors.color}
                            help="El que pidió el cliente. Se añade al nombre del producto en la orden, el certificado y el QR."
                        >
                            <Input
                                value={data.color}
                                maxLength={100}
                                onChange={(e) =>
                                    setData('color', e.target.value)
                                }
                                placeholder="Ej.: RAL 3020"
                            />
                        </FormField>

                        <FormField
                            id="notes"
                            label="Notas / Observaciones"
                            error={errors.notes}
                        >
                            <Textarea
                                maxLength={2000}
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Instrucciones especiales para esta producción..."
                                className="min-h-[80px]"
                            />
                        </FormField>
                    </div>

                    {/* Plan de Envasado */}
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                                className="self-start sm:self-auto"
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Agregar Presentación
                            </Button>
                        </div>

                        {data.packaging.length === 0 ? (
                            <div className="rounded border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                                {!data.product_id
                                    ? 'Selecciona un producto para ver las presentaciones disponibles'
                                    : availableVariants.length === 0
                                      ? 'Este producto no tiene variantes definidas'
                                      : "Haz clic en 'Agregar Presentación' para definir el plan de envasado"}
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {data.packaging.map((pack, index) => (
                                    <FormLineItem
                                        key={index}
                                        index={index}
                                        labelsOnFirstRowOnly
                                        // El plan de envasado es opcional: toda fila se puede quitar, también la última.
                                        onRemove={() => removePackaging(index)}
                                        className="md:grid-cols-12"
                                    >
                                        <FormField
                                            id={`packaging.${index}.product_variant_id`}
                                            label="Presentación"
                                            required
                                            error={packagingRowError(
                                                index,
                                                'product_variant_id',
                                            )}
                                            className="md:col-span-8"
                                        >
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
                                        </FormField>

                                        <FormField
                                            id={`packaging.${index}.planned_units`}
                                            label="Unidades a Producir"
                                            required
                                            error={packagingRowError(
                                                index,
                                                'planned_units',
                                            )}
                                            className="md:col-span-4"
                                        >
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
                                        </FormField>
                                    </FormLineItem>
                                ))}
                            </div>
                        )}

                        <InputError
                            message={
                                (errors as Record<string, string>).packaging
                            }
                        />
                    </div>

                    <FormActions
                        submitLabel="Crear Orden de Producción"
                        processing={processing}
                        cancelHref={productionOrderIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ProductionOrdersCreate.layout = {
    breadcrumbs: [
        { title: 'Órdenes de producción', href: productionOrderIndex() },
        { title: 'Nueva', href: productionOrderCreate() },
    ],
};
