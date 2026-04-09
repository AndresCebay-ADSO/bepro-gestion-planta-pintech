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

/**
 * Tipos
 */
type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type RawMaterialFormData = {
    code: string;
    unit_of_measure_id: string;
    current_price: string;
    previous_price: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    is_active: boolean;
};

/**
 * 🔥 SOLO campos string (clave de la solución)
 */
type StringFields = {
    [K in keyof RawMaterialFormData]: RawMaterialFormData[K] extends string ? K : never;
}[keyof RawMaterialFormData];

/**
 * Tipo fuerte para Inertia Form
 */
type InertiaForm<T> = {
    data: T;
    setData: <K extends keyof T>(key: K, value: T[K]) => void;
    processing: boolean;
    errors: Partial<Record<keyof T, string>>;
};

type Props = {
    form: InertiaForm<RawMaterialFormData>;
    units: UnitOption[];
    onSubmit: () => void;
    submitLabel: string;
};

const MAX_DECIMAL_INTEGER_DIGITS = 14;
const MAX_DECIMAL_FRACTION_DIGITS = 4;
const MAX_DECIMAL_INPUT_LENGTH = MAX_DECIMAL_INTEGER_DIGITS + 1 + MAX_DECIMAL_FRACTION_DIGITS;
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
 * Componente reutilizable de formulario
 */
export function RawMaterialForm({
    form,
    units,
    onSubmit,
    submitLabel,
}: Props) {
    /**
     * Submit limpio
     */
    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        onSubmit();
    };

    /**
     * 🔥 Handler SOLO para campos string
     */
    const handleChange =
        <K extends StringFields>(key: K) =>
        (e: ChangeEvent<HTMLInputElement>) => {
            form.setData(key, e.target.value);
        };

    return (
        <form onSubmit={handleSubmit} className="grid min-w-0 gap-5">
            {/* Código */}
            <div className="grid min-w-0 gap-2">
                <Label htmlFor="code">Código interno</Label>
                <Input
                    id="code"
                    value={form.data.code}
                    onChange={handleChange('code')}
                    maxLength={50}
                    className="w-full min-w-0"
                />
                <InputError message={form.errors.code} />
            </div>

            {/* Unidad */}
            <div className="grid min-w-0 gap-2">
                <Label htmlFor="unit">Unidad de medida</Label>
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
                            <SelectItem key={unit.id} value={String(unit.id)}>
                                {unit.name} ({unit.symbol})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <InputError message={form.errors.unit_of_measure_id} />
            </div>

            {/* Precios */}
            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="current_price">Precio actual</Label>
                    <Input
                        id="current_price"
                        type="text"
                        inputMode="decimal"
                        maxLength={MAX_DECIMAL_INPUT_LENGTH}
                        value={form.data.current_price}
                        onChange={(event) =>
                            form.setData('current_price', sanitizeDecimalInput(event.target.value))
                        }
                        className="w-full min-w-0"
                    />
                    {form.data.current_price && (
                        <p className="min-w-0 break-all text-xs text-muted-foreground">
                            <FormattedNumber
                                value={form.data.current_price}
                                currency
                            />
                        </p>
                    )}
                    <InputError message={form.errors.current_price} />
                </div>

                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="previous_price">
                        Precio anterior (opcional)
                    </Label>
                    <Input
                        id="previous_price"
                        type="text"
                        inputMode="decimal"
                        maxLength={MAX_DECIMAL_INPUT_LENGTH}
                        value={form.data.previous_price}
                        onChange={(event) =>
                            form.setData('previous_price', sanitizeDecimalInput(event.target.value))
                        }
                        className="w-full min-w-0"
                    />
                    {form.data.previous_price && (
                        <p className="min-w-0 break-all text-xs text-muted-foreground italic">
                            <FormattedNumber
                                value={form.data.previous_price}
                                currency
                            />
                        </p>
                    )}
                    <InputError message={form.errors.previous_price} />
                </div>
            </div>

            {/* Stock */}
            <div className="grid min-w-0 gap-4 md:grid-cols-2">
                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="minimum_stock">Stock mínimo</Label>
                    <Input
                        id="minimum_stock"
                        type="text"
                        inputMode="decimal"
                        maxLength={MAX_DECIMAL_INPUT_LENGTH}
                        value={form.data.minimum_stock}
                        onChange={(event) =>
                            form.setData('minimum_stock', sanitizeDecimalInput(event.target.value))
                        }
                        className="w-full min-w-0"
                    />
                    {form.data.minimum_stock && (
                        <p className="min-w-0 break-all text-xs text-muted-foreground">
                            <FormattedNumber
                                value={form.data.minimum_stock}
                            />
                        </p>
                    )}
                    <InputError message={form.errors.minimum_stock} />
                </div>

                <div className="grid min-w-0 gap-2">
                    <Label htmlFor="alert_days">
                        Días de alerta por vencimiento
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
                                sanitizeIntegerInput(event.target.value, MAX_ALERT_DAYS_INPUT_LENGTH),
                            )
                        }
                        className="w-full min-w-0"
                    />
                    <InputError message={form.errors.alert_days_before_expiry} />
                </div>
            </div>

            {/* Estado */}
            <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2">
                <Checkbox
                    id="is_active"
                    checked={form.data.is_active}
                    onCheckedChange={(checked) =>
                        form.setData('is_active', checked === true)
                    }
                />
                <Label htmlFor="is_active" className="cursor-pointer">
                    Materia prima activa
                </Label>
            </div>

            {/* Botón */}
            <div className="flex gap-2">
                <Button type="submit" disabled={form.processing}>
                    {form.processing ? 'Procesando...' : submitLabel}
                </Button>
            </div>
        </form>
    );
}