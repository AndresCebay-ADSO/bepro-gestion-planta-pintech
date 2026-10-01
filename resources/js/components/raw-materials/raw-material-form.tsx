import React from 'react';
import type { ChangeEvent } from 'react';

import { FormattedNumber } from '@/components/formatted-number';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
    /** Puede fijar el precio de una materia prima sin control de inventario (`costs.update`). */
    canEditPrice: boolean;
};

// Las columnas son decimal(12,4): 8 dígitos enteros y 4 decimales.
const MAX_DECIMAL_INTEGER_DIGITS = 8;
const MAX_DECIMAL_FRACTION_DIGITS = 4;
const MAX_DECIMAL_INPUT_LENGTH =
    MAX_DECIMAL_INTEGER_DIGITS + 1 + MAX_DECIMAL_FRACTION_DIGITS;
const MAX_ALERT_DAYS_INPUT_LENGTH = 4;

function sanitizeDecimalInput(rawValue: string): string {
    const normalized = rawValue.replace(',', '.').replace(/[^\d.]/g, '');
    const [rawIntegerPart = '', rawFractionPart = ''] = normalized.split('.');
    const integerPart = rawIntegerPart.slice(0, MAX_DECIMAL_INTEGER_DIGITS);
    const hasDot = normalized.includes('.');

    if (!hasDot) {
        return integerPart;
    }

    const fractionPart = rawFractionPart.slice(0, MAX_DECIMAL_FRACTION_DIGITS);

    return `${integerPart}.${fractionPart}`;
}

function sanitizeIntegerInput(rawValue: string, maxLength: number): string {
    return rawValue.replace(/\D/g, '').slice(0, maxLength);
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
    canEditPrice,
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

    return (
        <form onSubmit={handleSubmit} className="grid min-w-0 gap-6">
            {/* Block 1: Identification */}
            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="font-medium text-foreground">Identificación</h2>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                    {/* Code */}
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="code">
                            Código de Referencia{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="code"
                            value={form.data.code}
                            onChange={handleChange('code')}
                            maxLength={50}
                            className="w-full min-w-0 font-mono"
                            placeholder="Ej. R01, T05"
                        />
                        <InputError message={form.errors.code} />
                    </div>

                    {/* Unit */}
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="unit">
                            Unidad de Medida{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={form.data.unit_of_measure_id}
                            onValueChange={(value) =>
                                form.setData('unit_of_measure_id', value)
                            }
                        >
                            <SelectTrigger id="unit" className="w-full min-w-0">
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
                        <InputError message={form.errors.unit_of_measure_id} />
                    </div>
                    {/* Category */}
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="category">
                            Categoría{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Select
                            value={form.data.category_id}
                            onValueChange={(value) =>
                                form.setData('category_id', value)
                            }
                        >
                            <SelectTrigger
                                id="category"
                                className="w-full min-w-0"
                            >
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
                        <InputError message={form.errors.category_id} />
                    </div>

                    {/* Tipo de insumo: lo decide la categoría, aquí solo se muestra */}
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="category_type">Tipo de insumo</Label>
                        <Input
                            id="category_type"
                            value={selectedCategory?.type_label ?? ''}
                            placeholder="Elige una categoría"
                            readOnly
                            tabIndex={-1}
                            className="bg-muted/40"
                        />
                        <p className="text-xs text-muted-foreground">
                            {selectedCategory
                                ? RAW_MATERIAL_TYPE_HINTS[selectedCategory.type]
                                : 'Lo define la categoría elegida.'}
                        </p>
                    </div>
                </div>
            </div>

            {/* Block 2: Inventory Control */}
            <div className="space-y-4 rounded-lg border border-border bg-card p-6 shadow-sm">
                <h2 className="font-medium text-foreground">
                    Control de Inventario
                </h2>
                <div className="grid min-w-0 gap-4 md:grid-cols-2">
                    {/* Stock */}
                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="minimum_stock">
                            Stock mínimo en planta{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="minimum_stock"
                            type="text"
                            inputMode="decimal"
                            maxLength={MAX_DECIMAL_INPUT_LENGTH}
                            value={form.data.minimum_stock}
                            onChange={(event) =>
                                form.setData(
                                    'minimum_stock',
                                    sanitizeDecimalInput(event.target.value),
                                )
                            }
                            className="w-full min-w-0 font-mono"
                            placeholder="0.00"
                        />
                        {form.data.minimum_stock && (
                            <p className="min-w-0 text-xs break-all text-muted-foreground">
                                Resguardo:{' '}
                                <FormattedNumber
                                    value={form.data.minimum_stock.replace(
                                        /\.$/,
                                        '',
                                    )}
                                />
                            </p>
                        )}
                        <InputError message={form.errors.minimum_stock} />
                    </div>

                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="alert_days">
                            Días de alerta por vencimiento{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Input
                            id="alert_days"
                            type="text"
                            inputMode="numeric"
                            maxLength={MAX_ALERT_DAYS_INPUT_LENGTH}
                            value={form.data.alert_days_before_expiry}
                            onChange={(event) =>
                                form.setData(
                                    'alert_days_before_expiry',
                                    sanitizeIntegerInput(
                                        event.target.value,
                                        MAX_ALERT_DAYS_INPUT_LENGTH,
                                    ),
                                )
                            }
                            className="w-full min-w-0"
                            placeholder="30"
                        />
                        <InputError
                            message={form.errors.alert_days_before_expiry}
                        />
                    </div>

                    <div className="grid min-w-0 gap-2">
                        <Label htmlFor="price_variation_threshold">
                            Umbral de alerta por variación de precio (%)
                        </Label>
                        <Input
                            id="price_variation_threshold"
                            type="text"
                            inputMode="decimal"
                            maxLength={6}
                            value={form.data.price_variation_threshold}
                            onChange={(event) =>
                                form.setData(
                                    'price_variation_threshold',
                                    sanitizeDecimalInput(event.target.value),
                                )
                            }
                            className="w-full min-w-0"
                            placeholder="Vacío = sin alerta"
                        />
                        <p className="text-xs text-muted-foreground">
                            Solo las materias primas con umbral configurado
                            generan alertas de variación de precio.
                        </p>
                        <InputError
                            message={form.errors.price_variation_threshold}
                        />
                    </div>
                </div>

                {/* Control de inventario y precio manual (decisión del 2026-09-30) */}
                <div className="mt-4 space-y-4 rounded-md border border-border p-4">
                    <div className="flex items-start gap-3">
                        <Checkbox
                            id="tracks_inventory"
                            checked={form.data.tracks_inventory}
                            // Cambiar el control (en cualquier sentido) cambia cómo se costea: solo quien maneja costos.
                            disabled={!canEditPrice}
                            onCheckedChange={(checked) =>
                                form.setData(
                                    'tracks_inventory',
                                    checked === true,
                                )
                            }
                        />
                        <div className="grid gap-1">
                            <Label
                                htmlFor="tracks_inventory"
                                className="cursor-pointer"
                            >
                                Controla inventario
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                Con control se compra por lotes, la orden de
                                producción descuenta el saldo y el precio sale
                                de las compras. Sin control (agua, etiquetas),
                                la orden registra el consumo sin descontar saldo
                                y lo costea con el precio que se escribe aquí.
                                {!canEditPrice &&
                                    ' Solo quien puede modificar los parámetros de costo cambia el control.'}
                            </p>
                        </div>
                    </div>
                    <InputError message={form.errors.tracks_inventory} />

                    {!form.data.tracks_inventory &&
                        (canEditPrice ? (
                            <div className="grid min-w-0 gap-2 md:max-w-xs">
                                <Label htmlFor="current_price">
                                    Precio de referencia
                                    {selectedUnit
                                        ? ` por ${selectedUnit.symbol}`
                                        : ''}{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Input
                                    id="current_price"
                                    type="text"
                                    inputMode="decimal"
                                    maxLength={MAX_DECIMAL_INPUT_LENGTH}
                                    value={form.data.current_price}
                                    onChange={(event) =>
                                        form.setData(
                                            'current_price',
                                            sanitizeDecimalInput(
                                                event.target.value,
                                            ),
                                        )
                                    }
                                    className="w-full min-w-0 font-mono"
                                    placeholder="0"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Obligatorio, aunque sea 0. Al cambiarlo se
                                    recalcula el costo de los productos que la
                                    usan.
                                </p>
                                <InputError
                                    message={form.errors.current_price}
                                />
                            </div>
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                El precio lo fija quien puede modificar los
                                parámetros de costo.
                            </p>
                        ))}
                </div>

                {/* Status */}
                <div className="mt-4 flex items-center gap-3">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <Label htmlFor="is_active" className="cursor-pointer">
                        Materia prima activa y disponible para compras
                    </Label>
                </div>
            </div>

            {/* Button */}
            <div className="flex justify-end gap-3 pt-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Procesando...' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
