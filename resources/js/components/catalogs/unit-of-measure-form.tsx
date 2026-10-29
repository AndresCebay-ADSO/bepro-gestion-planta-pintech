import type { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { DecimalInput } from '@/components/decimal-input';
import { FormCheckbox, FormError, FormField } from '@/components/form-field';
import { FormattedNumber } from '@/components/formatted-number';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { NUMBER_TYPES } from '@/lib/number-types';
import { toDecimalPayload } from '@/lib/numeric-input';
import type { UnitOfMeasureFormData, UnitOfMeasureUsage } from '@/types';

type ConversionKind = 'none' | 'weight' | 'volume';

const CONVERSION_TARGET: Record<Exclude<ConversionKind, 'none'>, string> = {
    weight: 'kg',
    volume: 'L',
};

/** Las equivalencias: se escriben con coma o punto y se convierten al enviar con `withDecimalPayloads`. */
export const UNIT_FACTOR_FIELDS = [
    'to_kg_conversion',
    'to_liter_conversion',
] as const;

function conversionKindOf(data: UnitOfMeasureFormData): ConversionKind {
    if (data.to_kg_conversion.trim() !== '') {
        return 'weight';
    }

    return data.to_liter_conversion.trim() !== '' ? 'volume' : 'none';
}

/** «1 gl = 3,7854 L», o `emptyLabel` si la unidad no se convierte. Lo usan el listado y la edición. */
export function UnitEquivalence({
    symbol,
    kg,
    liter,
    emptyLabel = 'No se convierte',
}: {
    symbol: string;
    kg: string | null;
    liter: string | null;
    emptyLabel?: string;
}) {
    // En la confirmación llega lo que se está escribiendo («1,5»).
    const factor = toDecimalPayload(kg || liter);

    if (!factor) {
        return <>{emptyLabel}</>;
    }

    return (
        <>
            1 {symbol} ={' '}
            <FormattedNumber
                value={factor}
                maxDecimals={NUMBER_TYPES.factor.maxDecimals}
            />{' '}
            {kg ? 'kg' : 'L'}
        </>
    );
}

/** Solo lo que convierte con la equivalencia: materias primas y líneas de fórmula. */
export function describeConversionUsage(usage: UnitOfMeasureUsage): string {
    return describeUnitUsage({
        ...usage,
        products: 0,
        product_variants: 0,
    });
}

/** «203 materias primas · 136 líneas de fórmula», o «Sin uso». */
export function describeUnitUsage(usage: UnitOfMeasureUsage): string {
    const parts: Array<[number, string, string]> = [
        [usage.raw_materials, 'materia prima', 'materias primas'],
        [usage.products, 'producto', 'productos'],
        [usage.product_variants, 'presentación', 'presentaciones'],
        [usage.formula_details, 'línea de fórmula', 'líneas de fórmula'],
    ];

    const used = parts
        .filter(([count]) => count > 0)
        .map(([count, one, many]) => `${count} ${count === 1 ? one : many}`);

    return used.length > 0 ? used.join(' · ') : 'Sin uso';
}

/**
 * Compara dos equivalencias como texto normalizado ("1.0000" = "1" = "1,0"), sin pasar por float.
 * Solo decide si mostrar la confirmación; el servidor vuelve a comparar con bcmath.
 */
export function sameFactor(a: string | null, b: string): boolean {
    const normalize = (value: string | null) => {
        const trimmed = toDecimalPayload(value);

        return trimmed.includes('.')
            ? trimmed.replace(/0+$/, '').replace(/\.$/, '')
            : trimmed;
    };

    return normalize(a) === normalize(b);
}

type Props = {
    form: ReturnType<typeof useForm<UnitOfMeasureFormData>>;
    /** En edición, qué materias primas y líneas de fórmula convierten con la unidad (null si ninguna). */
    usageSummary?: string | null;
};

export function UnitOfMeasureFields({ form, usageSummary = null }: Props) {
    const [kind, setKind] = useState<ConversionKind>(() =>
        conversionKindOf(form.data),
    );

    const factorField =
        kind === 'volume' ? 'to_liter_conversion' : 'to_kg_conversion';

    const changeKind = (next: ConversionKind) => {
        const factor =
            form.data.to_kg_conversion || form.data.to_liter_conversion;

        setKind(next);
        form.setData((data) => ({
            ...data,
            to_kg_conversion: next === 'weight' ? factor : '',
            to_liter_conversion: next === 'volume' ? factor : '',
        }));
    };

    return (
        <div className="grid gap-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    id="code"
                    label="Código"
                    help="Identificador corto; se guarda en minúsculas."
                    error={form.errors.code}
                >
                    <Input
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        maxLength={20}
                        placeholder="kg"
                        autoComplete="off"
                    />
                </FormField>

                <FormField
                    id="symbol"
                    label="Símbolo"
                    help="Lo que se muestra junto a las cantidades."
                    error={form.errors.symbol}
                >
                    <Input
                        value={form.data.symbol}
                        onChange={(event) =>
                            form.setData('symbol', event.target.value)
                        }
                        maxLength={10}
                        placeholder="kg"
                        autoComplete="off"
                    />
                </FormField>
            </div>

            <FormField id="name" label="Nombre" error={form.errors.name}>
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    placeholder="Kilogramo"
                />
            </FormField>

            <FormField
                id="description"
                label="Descripción"
                error={form.errors.description}
            >
                <Textarea
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    maxLength={1000}
                    rows={2}
                />
            </FormField>

            <fieldset className="grid gap-4 rounded-lg border border-border p-4">
                <legend className="px-1 text-sm font-medium">
                    Equivalencia
                </legend>
                <p className="text-xs text-muted-foreground">
                    Convierte las cantidades de las fórmulas a la unidad de cada
                    materia prima. Una unidad se convierte por peso o por
                    volumen, no por ambos; «Unidad», por ejemplo, no se
                    convierte.
                </p>

                <div className="grid gap-4 sm:grid-cols-2">
                    <FormField id="conversion_kind" label="Se convierte por">
                        {(control) => (
                            <Select
                                value={kind}
                                onValueChange={(value: ConversionKind) =>
                                    changeKind(value)
                                }
                            >
                                <SelectTrigger {...control}>
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">
                                        No se convierte
                                    </SelectItem>
                                    <SelectItem value="weight">
                                        Peso (kilogramos)
                                    </SelectItem>
                                    <SelectItem value="volume">
                                        Volumen (litros)
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        )}
                    </FormField>

                    {kind !== 'none' && (
                        // El `id` es la llave del error del factor que se escribe: un 422 lleva el foco aquí.
                        <FormField
                            id={factorField}
                            label={`1 ${form.data.symbol.trim() || 'unidad'} equivale a`}
                            error={
                                form.errors.to_kg_conversion ??
                                form.errors.to_liter_conversion
                            }
                        >
                            {(control) => (
                                <div className="flex items-start gap-2">
                                    <DecimalInput
                                        {...control}
                                        containerClassName="min-w-0 flex-1"
                                        // numeric(14,6): 6 decimales (un mililitro son 0,001 L).
                                        {...NUMBER_TYPES.factor}
                                        value={form.data[factorField]}
                                        onValueChange={(value) =>
                                            form.setData(factorField, value)
                                        }
                                    />
                                    <span className="text-sm leading-9 text-muted-foreground">
                                        {CONVERSION_TARGET[kind]}
                                    </span>
                                </div>
                            )}
                        </FormField>
                    )}
                </div>

                {/* Sin factor («No se convierte») el error del factor no tiene campo donde mostrarse. */}
                {kind === 'none' && (
                    <FormError
                        message={
                            form.errors.to_kg_conversion ??
                            form.errors.to_liter_conversion
                        }
                    />
                )}

                {usageSummary && (
                    <p className="text-xs text-warning-text">
                        Esta equivalencia la usan {usageSummary}. Si la cambias,
                        se te pedirá confirmarlo.
                    </p>
                )}
                <FormError
                    id="confirm_factor_change"
                    message={form.errors.confirm_factor_change}
                />
            </fieldset>

            <FormCheckbox
                id="is_active"
                label="Unidad activa"
                help={
                    form.data.is_active
                        ? undefined
                        : 'Inactiva, no se podrá elegir en nuevas materias primas, productos, presentaciones ni fórmulas. Lo que ya la usa la conserva.'
                }
                error={form.errors.is_active}
                checked={form.data.is_active}
                onCheckedChange={(checked) =>
                    form.setData('is_active', checked === true)
                }
            />
        </div>
    );
}
