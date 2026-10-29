import React from 'react';
import type { ChangeEvent } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormCheckbox, FormField } from '@/components/form-field';
import { FormattedNumber } from '@/components/formatted-number';
import { IntegerInput } from '@/components/integer-input';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { NUMBER_TYPES } from '@/lib/number-types';
import { idPayload, parseDecimal, toDecimalPayload } from '@/lib/numeric-input';
import { RAW_MATERIAL_TYPE_HINTS } from '@/lib/raw-material-types';
import type { RawMaterialType } from '@/types';

/**
 * Types
 */
type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

export type CategoryOption = {
    id: number;
    name: string;
    code: string;
    /** Tipo de insumo de la categoría: la materia prima lo hereda. */
    type: RawMaterialType;
    type_label: string;
};

export type RawMaterialFormData = {
    code: string;
    category_id: string;
    unit_of_measure_id: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    price_variation_threshold: string;
    tracks_inventory: boolean;
    /** Precio de referencia escrito a mano: solo sin control de inventario y con permiso de costos. */
    current_price: string;
    is_active: boolean;
};

/**
 * Only string fields of RawMaterialFormData
 */
type StringFields = {
    [K in keyof RawMaterialFormData]: RawMaterialFormData[K] extends string
        ? K
        : never;
}[keyof RawMaterialFormData];

/**
 * Strongly-typed Inertia form helper
 */
type InertiaForm<T> = {
    data: T;
    setData: <K extends keyof T>(key: K, value: T[K]) => void;
    processing: boolean;
    errors: Partial<Record<keyof T, string>>;
};

type Props = {
    form: InertiaForm<RawMaterialFormData>;
    categories: CategoryOption[];
    units: UnitOption[];
    onSubmit: () => void;
    submitLabel: string;
    /** «Cancelar»: vuelve al listado. */
    cancelHref: string;
    /** Puede fijar el precio de una materia prima sin control de inventario (`costs.update`). */
    canEditPrice: boolean;
    /** Con historial o en uso (lotes, movimientos, fórmulas, envase o etiqueta) la unidad no cambia (RM-04): el servidor lo niega. */
    unitLocked?: boolean;
};

// Stock mínimo y precio: decimal(12,4), los límites por defecto de DecimalInput. Umbral: hasta 100 con 2 decimales.
const MAX_ALERT_DAYS_DIGITS = 4;

/**
 * Lo que se envía al crear o editar: decimales con punto, el umbral vacío como null, y el precio manual solo si se puede
 * escribir (sin control de inventario y con permiso de costos).
 */
export function rawMaterialPayload<T extends RawMaterialFormData>(
    { current_price, ...data }: T,
    canEditPrice: boolean,
) {
    return {
        ...data,
        unit_of_measure_id: idPayload(data.unit_of_measure_id),
        minimum_stock: toDecimalPayload(data.minimum_stock),
        price_variation_threshold:
            data.price_variation_threshold === ''
                ? null
                : toDecimalPayload(data.price_variation_threshold),
        ...(!data.tracks_inventory && canEditPrice
            ? {
                  current_price:
                      current_price === ''
                          ? null
                          : toDecimalPayload(current_price),
              }
            : {}),
    };
}

/**
 * Reusable raw material form
 */
export function RawMaterialForm({
    form,
    categories,
    units,
    onSubmit,
    submitLabel,
    cancelHref,
    canEditPrice,
    unitLocked = false,
}: Props) {
    /**
     * Clean submit handler
     */
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        onSubmit();
    };

    /**
     * Handler for string fields only
     */
    const handleChange =
        <K extends StringFields>(key: K) =>
        (e: ChangeEvent<HTMLInputElement>) => {
            form.setData(key, e.target.value);
        };

    const selectedCategory = categories.find(
        (category) => String(category.id) === form.data.category_id,
    );
    const selectedUnit = units.find(
        (unit) => String(unit.id) === form.data.unit_of_measure_id,
    );
    const minimumStock = parseDecimal(form.data.minimum_stock);

    return (
        <form noValidate onSubmit={handleSubmit} className="grid min-w-0 gap-6">
            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="font-medium text-foreground">Identificación</h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <FormField
                        id="code"
                        label="Código de referencia"
                        required
                        error={form.errors.code}
                    >
                        <Input
                            value={form.data.code}
                            onChange={handleChange('code')}
                            maxLength={50}
                            className="font-mono uppercase placeholder:normal-case"
                            placeholder="Ej. R01, T05"
                        />
                    </FormField>

                    <FormField
                        id="unit_of_measure_id"
                        label="Unidad de medida"
                        required
                        help={
                            unitLocked
                                ? 'No se puede cambiar: la materia prima ya tiene historial o está en uso.'
                                : undefined
                        }
                        error={form.errors.unit_of_measure_id}
                    >
                        {(control) => (
                            <Select
                                value={form.data.unit_of_measure_id}
                                onValueChange={(value) =>
                                    form.setData('unit_of_measure_id', value)
                                }
                                disabled={unitLocked}
                            >
                                <SelectTrigger {...control}>
                                    <SelectValue placeholder="Seleccionar unidad" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem
                                            key={unit.id}
                                            value={String(unit.id)}
                                        >
                                            {unit.name} ({unit.symbol})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

                    <FormField
                        id="category_id"
                        label="Categoría"
                        required
                        error={form.errors.category_id}
                    >
                        {(control) => (
                            <Select
                                value={form.data.category_id}
                                onValueChange={(value) =>
                                    form.setData('category_id', value)
                                }
                            >
                                <SelectTrigger {...control}>
                                    <SelectValue placeholder="Seleccionar categoría" />
                                </SelectTrigger>
                                <SelectContent>
                                    {categories.map((category) => (
                                        <SelectItem
                                            key={category.id}
                                            value={String(category.id)}
                                        >
                                            {category.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

                    {/* Tipo de insumo: lo decide la categoría, aquí solo se muestra */}
                    <FormField
                        id="category_type"
                        label="Tipo de insumo"
                        help={
                            selectedCategory
                                ? RAW_MATERIAL_TYPE_HINTS[selectedCategory.type]
                                : 'Lo define la categoría elegida.'
                        }
                    >
                        <Input
                            value={selectedCategory?.type_label ?? ''}
                            placeholder="Elige una categoría"
                            readOnly
                            tabIndex={-1}
                            className="bg-muted/40"
                        />
                    </FormField>
                </div>
            </div>

            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="font-medium text-foreground">
                    Control de inventario
                </h2>
                <div className="grid min-w-0 gap-4 md:grid-cols-2">
                    <FormField
                        id="minimum_stock"
                        label="Stock mínimo en planta"
                        required
                        help={
                            minimumStock !== null && (
                                <span className="break-all">
                                    Resguardo:{' '}
                                    <FormattedNumber value={minimumStock} />
                                </span>
                            )
                        }
                        error={form.errors.minimum_stock}
                    >
                        <DecimalInput
                            value={form.data.minimum_stock}
                            onValueChange={(value) =>
                                form.setData('minimum_stock', value)
                            }
                            className="font-mono"
                            placeholder="0"
                        />
                    </FormField>

                    <FormField
                        id="alert_days_before_expiry"
                        label="Días de alerta por vencimiento"
                        required
                        error={form.errors.alert_days_before_expiry}
                    >
                        <IntegerInput
                            maxDigits={MAX_ALERT_DAYS_DIGITS}
                            value={form.data.alert_days_before_expiry}
                            onValueChange={(value) =>
                                form.setData('alert_days_before_expiry', value)
                            }
                            placeholder="30"
                        />
                    </FormField>

                    <FormField
                        id="price_variation_threshold"
                        label="Umbral de alerta por variación de precio (%)"
                        help="Solo las materias primas con umbral configurado generan alertas de variación de precio."
                        error={form.errors.price_variation_threshold}
                    >
                        <DecimalInput
                            maxDecimals={2}
                            maxIntegerDigits={3}
                            value={form.data.price_variation_threshold}
                            onValueChange={(value) =>
                                form.setData('price_variation_threshold', value)
                            }
                            placeholder="Vacío = sin alerta"
                        />
                    </FormField>
                </div>

                {/* Control de inventario y precio manual (decisión del 2026-09-30) */}
                <div className="space-y-4 rounded-md border border-border p-4">
                    <FormCheckbox
                        id="tracks_inventory"
                        label="Controla inventario"
                        help={
                            <>
                                Con control se compra por lotes, la orden de
                                producción descuenta el saldo y el precio sale
                                de las compras. Sin control (agua, etiquetas),
                                la orden registra el consumo sin descontar saldo
                                y lo costea con el precio que se escribe aquí.
                                {!canEditPrice &&
                                    ' Solo quien puede modificar los parámetros de costo cambia el control.'}
                            </>
                        }
                        error={form.errors.tracks_inventory}
                        checked={form.data.tracks_inventory}
                        // Cambiar el control (en cualquier sentido) cambia cómo se costea: solo quien maneja costos.
                        disabled={!canEditPrice}
                        onCheckedChange={(checked) =>
                            form.setData('tracks_inventory', checked === true)
                        }
                    />

                    {!form.data.tracks_inventory &&
                        (canEditPrice ? (
                            <FormField
                                id="current_price"
                                label={`Precio de referencia${selectedUnit ? ` por ${selectedUnit.symbol}` : ''}`}
                                required
                                help="Obligatorio, aunque sea 0. Al cambiarlo se recalcula el costo de los productos que la usan."
                                error={form.errors.current_price}
                                className="md:max-w-xs"
                            >
                                <DecimalInput
                                    {...NUMBER_TYPES.money}
                                    value={form.data.current_price}
                                    onValueChange={(value) =>
                                        form.setData('current_price', value)
                                    }
                                    className="font-mono"
                                    placeholder="0"
                                />
                            </FormField>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                El precio lo fija quien puede modificar los
                                parámetros de costo.
                            </p>
                        ))}
                </div>

                <FormCheckbox
                    id="is_active"
                    label="Materia prima activa y disponible para compras"
                    error={form.errors.is_active}
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
            </div>

            <FormActions
                submitLabel={submitLabel}
                processing={form.processing}
                cancelHref={cancelHref}
            />
        </form>
    );
}
