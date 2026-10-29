import type { FormEvent } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormCheckbox, FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { PRODUCT_DECIMAL_LIMITS } from '@/lib/product-form';

type Option = { id: number; name: string; symbol?: string };

export type ProductFormData = {
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

type QualityField = keyof typeof PRODUCT_DECIMAL_LIMITS & `quality_${string}`;

type Props = {
    data: ProductFormData;
    setData: <K extends keyof ProductFormData>(
        key: K,
        value: ProductFormData[K],
    ) => void;
    errors: Partial<Record<keyof ProductFormData, string>>;
    processing: boolean;
    categories: Option[];
    units: Option[];
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    submitLabel: string;
    cancelHref: string;
    /** CIF y umbral: solo con permiso de precios (y, al editar, de ver costos). */
    showPrices: boolean;
    /** Costo y precio guardados, de solo lectura (vacíos al crear). */
    currentCost?: string;
    currentPrice?: string;
    /** De dónde sale el costo: al crear, aún no hay fórmula. */
    costHelp: string;
    /** «Producto activo»: al editar, con permiso de desactivar. */
    showActive?: boolean;
};

/** Crear y editar un producto (DESIGN.md §8): los mismos campos, con `<FormField>` y `<FormActions>`. */
export function ProductForm({
    data,
    setData,
    errors,
    processing,
    categories,
    units,
    onSubmit,
    submitLabel,
    cancelHref,
    showPrices,
    currentCost = '',
    currentPrice = '',
    costHelp,
    showActive = false,
}: Props) {
    /** Un rango de calidad: mínimo y máximo, agrupados bajo su medida. */
    const qualityRange = (
        legend: string,
        lower: QualityField,
        upper: QualityField,
        placeholders: [string, string] = ['', ''],
    ) => (
        <fieldset className="min-w-0">
            <legend className="mb-3 text-sm font-medium">{legend}</legend>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                {(
                    [
                        [lower, 'Mínimo', placeholders[0]],
                        [upper, 'Máximo', placeholders[1]],
                    ] as const
                ).map(([field, label, placeholder]) => (
                    <FormField
                        key={field}
                        id={field}
                        label={label}
                        error={errors[field]}
                    >
                        <DecimalInput
                            {...PRODUCT_DECIMAL_LIMITS[field]}
                            value={data[field]}
                            onValueChange={(value) => setData(field, value)}
                            placeholder={placeholder || undefined}
                        />
                    </FormField>
                ))}
            </div>
        </fieldset>
    );

    return (
        <form noValidate onSubmit={onSubmit} className="space-y-6">
            <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                <h2 className="text-lg font-semibold text-foreground">
                    Identificación
                </h2>

                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField
                        id="name"
                        label="Nombre"
                        required
                        error={errors.name}
                        className="md:col-span-2"
                    >
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            maxLength={150}
                            className="uppercase placeholder:normal-case"
                            placeholder="Nombre descriptivo del producto"
                        />
                    </FormField>

                    <FormField
                        id="category_id"
                        label="Categoría"
                        required
                        error={errors.category_id}
                    >
                        {(control) => (
                            <Select
                                value={data.category_id}
                                onValueChange={(v) => setData('category_id', v)}
                            >
                                <SelectTrigger {...control}>
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
                        )}
                    </FormField>

                    <FormField
                        id="unit_of_measure_id"
                        label="Unidad de medida"
                        required
                        error={errors.unit_of_measure_id}
                    >
                        {(control) => (
                            <Select
                                value={data.unit_of_measure_id}
                                onValueChange={(v) =>
                                    setData('unit_of_measure_id', v)
                                }
                            >
                                <SelectTrigger {...control}>
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
                        )}
                    </FormField>

                    <FormField
                        id="description"
                        label="Descripción"
                        help="Puede mostrarse en la ficha pública del QR."
                        error={errors.description}
                        className="md:col-span-2"
                    >
                        <Textarea
                            rows={4}
                            value={data.description}
                            onChange={(e) =>
                                setData('description', e.target.value)
                            }
                            placeholder="Resumen del producto, usos recomendados, notas para comercial o calidad…"
                            className="min-h-[100px] resize-y"
                        />
                    </FormField>
                </div>

                {showActive && (
                    <FormCheckbox
                        id="is_active"
                        label="Producto activo"
                        error={errors.is_active}
                        checked={data.is_active}
                        onCheckedChange={(checked) =>
                            setData('is_active', checked === true)
                        }
                    />
                )}
            </div>

            <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                <div>
                    <h2 className="text-lg font-semibold text-foreground">
                        Rangos para certificado de calidad
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        Límites de referencia comparados con viscosidad (KU),
                        molienda (HG) y sólidos (%) al cerrar la orden. Dejar
                        vacío si no aplica.
                    </p>
                </div>
                <div className="space-y-6">
                    {qualityRange(
                        'Viscosidad (KU)',
                        'quality_viscosity_lower',
                        'quality_viscosity_upper',
                        ['Ej: 90', 'Ej: 110'],
                    )}
                    {qualityRange(
                        'Molienda (HG)',
                        'quality_fineness_lower',
                        'quality_fineness_upper',
                    )}
                    {qualityRange(
                        'Sólidos (%)',
                        'quality_solids_lower',
                        'quality_solids_upper',
                    )}
                </div>
            </div>

            {showPrices && (
                <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                    <div>
                        <h2 className="text-lg font-semibold text-foreground">
                            Precios y costos
                        </h2>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Define CIF y umbral para cálculo automático de
                            precios.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <FormField
                            id="current_cost"
                            label="Costo actual"
                            help={costHelp}
                        >
                            <Input
                                value={currentCost}
                                readOnly
                                className="cursor-not-allowed bg-muted/50"
                                placeholder="0"
                            />
                        </FormField>

                        <FormField
                            id="current_price"
                            label="Precio interno"
                            help="Se calcula automáticamente: costo actual × (1 + CIF %)."
                        >
                            <Input
                                value={currentPrice}
                                readOnly
                                className="cursor-not-allowed bg-muted/50"
                                placeholder="0"
                            />
                        </FormField>

                        <FormField
                            id="cif_percentage"
                            label="CIF (%)"
                            required
                            error={errors.cif_percentage}
                        >
                            <DecimalInput
                                {...PRODUCT_DECIMAL_LIMITS.cif_percentage}
                                value={data.cif_percentage}
                                onValueChange={(value) =>
                                    setData('cif_percentage', value)
                                }
                                placeholder="0"
                            />
                        </FormField>

                        <FormField
                            id="price_threshold"
                            label="Umbral mínimo de precio (%)"
                            error={errors.price_threshold}
                        >
                            <DecimalInput
                                {...PRODUCT_DECIMAL_LIMITS.price_threshold}
                                value={data.price_threshold}
                                onValueChange={(value) =>
                                    setData('price_threshold', value)
                                }
                            />
                        </FormField>
                    </div>
                </div>
            )}

            <FormActions
                submitLabel={submitLabel}
                processing={processing}
                cancelHref={cancelHref}
            />
        </form>
    );
}
