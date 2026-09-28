import type { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FormattedNumber } from '@/components/formatted-number';
import InputError from '@/components/input-error';
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
import { Textarea } from '@/components/ui/textarea';
import type { UnitOfMeasureFormData, UnitOfMeasureUsage } from '@/types';

type ConversionKind = 'none' | 'weight' | 'volume';

const CONVERSION_TARGET: Record<Exclude<ConversionKind, 'none'>, string> = {
    weight: 'kg',
    volume: 'L',
};

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
    const factor = kg || liter;

    if (!factor) {
        return <>{emptyLabel}</>;
    }

    return (
        <>
            1 {symbol} = <FormattedNumber value={factor} maxDecimals={4} />{' '}
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
 * Compara dos equivalencias como texto normalizado ("1.0000" = "1"), sin pasar por float.
 * Solo decide si mostrar la confirmación; el servidor vuelve a comparar con bcmath.
 */
export function sameFactor(a: string | null, b: string): boolean {
    const normalize = (value: string | null) => {
        const trimmed = (value ?? '').trim();

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
                <div className="grid gap-2">
                    <Label htmlFor="code">Código</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        maxLength={20}
                        placeholder="kg"
                        autoComplete="off"
                    />
                    <p className="text-xs text-muted-foreground">
                        Identificador corto; se guarda en minúsculas.
                    </p>
                    <InputError message={form.errors.code} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="symbol">Símbolo</Label>
                    <Input
                        id="symbol"
                        value={form.data.symbol}
                        onChange={(event) =>
                            form.setData('symbol', event.target.value)
                        }
                        maxLength={10}
                        placeholder="kg"
                        autoComplete="off"
                    />
                    <p className="text-xs text-muted-foreground">
                        Lo que se muestra junto a las cantidades.
                    </p>
                    <InputError message={form.errors.symbol} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    placeholder="Kilogramo"
                />
                <InputError message={form.errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Descripción</Label>
                <Textarea
                    id="description"
                    value={form.data.description}
                    onChange={(event) =>
                        form.setData('description', event.target.value)
                    }
                    maxLength={1000}
                    rows={2}
                />
                <InputError message={form.errors.description} />
            </div>

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
                    <div className="grid gap-2">
                        <Label htmlFor="conversion_kind">
                            Se convierte por
                        </Label>
                        <Select
                            value={kind}
                            onValueChange={(value: ConversionKind) =>
                                changeKind(value)
                            }
                        >
                            <SelectTrigger id="conversion_kind">
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
                    </div>

                    {kind !== 'none' && (
                        <div className="grid gap-2">
                            <Label htmlFor="factor">
                                1 {form.data.symbol.trim() || 'unidad'} equivale
                                a
                            </Label>
                            <div className="flex items-center gap-2">
                                <Input
                                    id="factor"
                                    type="number"
                                    inputMode="decimal"
                                    step="0.0001"
                                    min="0.0001"
                                    value={form.data[factorField]}
                                    onChange={(event) =>
                                        form.setData(
                                            factorField,
                                            event.target.value,
                                        )
                                    }
                                />
                                <span className="text-sm text-muted-foreground">
                                    {CONVERSION_TARGET[kind]}
                                </span>
                            </div>
                        </div>
                    )}
                </div>

                <InputError
                    message={
                        form.errors.to_kg_conversion ??
                        form.errors.to_liter_conversion
                    }
                />

                {usageSummary && (
                    <p className="text-xs text-amber-700 dark:text-amber-300">
                        Esta equivalencia la usan {usageSummary}. Si la cambias,
                        se te pedirá confirmarlo.
                    </p>
                )}
                <InputError message={form.errors.confirm_factor_change} />
            </fieldset>

            <div className="grid gap-2">
                <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2">
                    <Checkbox
                        id="is_active"
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', checked === true)
                        }
                    />
                    <Label htmlFor="is_active" className="cursor-pointer">
                        Unidad activa
                    </Label>
                </div>
                {!form.data.is_active && (
                    <p className="text-xs text-muted-foreground">
                        Inactiva, no se podrá elegir en nuevas materias primas,
                        productos, presentaciones ni fórmulas. Lo que ya la usa
                        la conserva.
                    </p>
                )}
                <InputError message={form.errors.is_active} />
            </div>
        </div>
    );
}
