import { Plus, Trash2 } from 'lucide-react';
import type { FormEvent } from 'react';

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

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; code: string; name: string };
type UnitOption = { id: number; name: string; symbol: string };

export type DetailRow = {
    raw_material_id: string;
    quantity: string;
    unit_of_measure_id: string;
};

export type FormulaFormData = {
    product_id: string;
    notes: string;
    details: DetailRow[];
};

type FormulaFormProps = {
    data: FormulaFormData;
    errors: Record<string, string>;
    processing: boolean;
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
    submitLabel: string;
    heading: string;
    description: string;
    onSubmit: (event: FormEvent<HTMLFormElement>) => void;
    setData: <K extends keyof FormulaFormData>(
        key: K,
        value: FormulaFormData[K],
    ) => void;
    lockProduct?: boolean;
};

const emptyDetail = (): DetailRow => ({
    raw_material_id: '',
    quantity: '',
    unit_of_measure_id: '',
});

export function createEmptyFormulaDetail(): DetailRow {
    return emptyDetail();
}

export function FormulaForm({
    data,
    errors,
    processing,
    products,
    rawMaterials,
    units,
    submitLabel,
    heading,
    description,
    onSubmit,
    setData,
    lockProduct = false,
}: FormulaFormProps) {
    const productOptions = products.map((product) => ({
        id: String(product.id),
        label: `${product.code} — ${product.name}`,
    }));

    const rawMaterialOptions = rawMaterials.map((rawMaterial) => ({
        id: String(rawMaterial.id),
        label: rawMaterial.code,
    }));

    const addDetail = () => {
        setData('details', [...data.details, emptyDetail()]);
    };

    const removeDetail = (index: number) => {
        setData(
            'details',
            data.details.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const updateDetail = (
        index: number,
        field: keyof DetailRow,
        value: string,
    ) => {
        setData(
            'details',
            data.details.map((detail, currentIndex) =>
                currentIndex === index
                    ? { ...detail, [field]: value }
                    : detail,
            ),
        );
    };

    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <div className="max-w-2xl space-y-4 rounded-lg border border-border bg-card p-6">
                <h2 className="font-medium text-foreground">{heading}</h2>

                <div className="space-y-2">
                    <Label htmlFor="product_id">Producto *</Label>
                    <Combobox
                        options={productOptions}
                        value={data.product_id}
                        onChange={(value) =>
                            setData('product_id', String(value))
                        }
                        placeholder="Busca o selecciona el producto..."
                        disabled={lockProduct}
                    />
                    {lockProduct && (
                        <p className="text-xs text-muted-foreground">
                            El producto no se puede cambiar al editar una
                            fórmula existente.
                        </p>
                    )}
                    {errors.product_id && (
                        <p className="text-sm text-destructive">
                            {errors.product_id}
                        </p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="notes">Notas / Observaciones</Label>
                    <Textarea
                        id="notes"
                        value={data.notes}
                        onChange={(event) =>
                            setData('notes', event.target.value)
                        }
                        placeholder="Ej: Fórmula para tono azul celeste estándar, sin modificaciones..."
                        className="min-h-[80px]"
                    />
                    {errors.notes && (
                        <p className="text-sm text-destructive">
                            {errors.notes}
                        </p>
                    )}
                </div>
            </div>

            <div className="rounded-lg border border-border bg-card">
                <div className="flex items-center justify-between border-b border-border px-6 py-4">
                    <div>
                        <h2 className="font-medium text-foreground">
                            Ingredientes por Galón
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {description}
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addDetail}
                    >
                        <Plus className="mr-1 h-4 w-4" />
                        Agregar ingrediente
                    </Button>
                </div>

                <div className="divide-y divide-border">
                    {data.details.map((detail, index) => (
                        <div
                            key={index}
                            className="grid grid-cols-12 items-end gap-3 p-4"
                        >
                            <div className="col-span-1 space-y-1">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Paso
                                    </Label>
                                )}
                                <p className="py-2 text-center text-sm font-medium tabular-nums text-muted-foreground">
                                    {index + 1}
                                </p>
                            </div>
                            <div className="col-span-4 space-y-1">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Materia Prima
                                    </Label>
                                )}
                                <Combobox
                                    options={rawMaterialOptions}
                                    value={detail.raw_material_id}
                                    onChange={(value) =>
                                        updateDetail(
                                            index,
                                            'raw_material_id',
                                            String(value),
                                        )
                                    }
                                    placeholder="Materia..."
                                />
                                {errors[`details.${index}.raw_material_id`] && (
                                    <p className="text-xs text-destructive">
                                        {
                                            errors[
                                                `details.${index}.raw_material_id`
                                            ]
                                        }
                                    </p>
                                )}
                            </div>

                            <div className="col-span-3 space-y-1">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Cantidad (por galón)
                                    </Label>
                                )}
                                <Input
                                    type="text"
                                    inputMode="decimal"
                                    value={detail.quantity}
                                    onChange={(event) => {
                                        const value = event.target.value;

                                        if (
                                            value === '' ||
                                            /^\d*[.,]?\d{0,4}$/.test(value)
                                        ) {
                                            updateDetail(
                                                index,
                                                'quantity',
                                                value,
                                            );
                                        }
                                    }}
                                    placeholder="Ej: 1,5"
                                />
                                {errors[`details.${index}.quantity`] && (
                                    <p className="text-xs text-destructive">
                                        {errors[`details.${index}.quantity`]}
                                    </p>
                                )}
                            </div>

                            <div className="col-span-3 space-y-1">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Unidad
                                    </Label>
                                )}
                                <Select
                                    value={detail.unit_of_measure_id}
                                    onValueChange={(value) =>
                                        updateDetail(
                                            index,
                                            'unit_of_measure_id',
                                            value,
                                        )
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Unidad..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {units.map((unit) => (
                                            <SelectItem
                                                key={unit.id}
                                                value={String(unit.id)}
                                            >
                                                {unit.symbol}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors[`details.${index}.unit_of_measure_id`] && (
                                    <p className="text-xs text-destructive">
                                        {
                                            errors[
                                                `details.${index}.unit_of_measure_id`
                                            ]
                                        }
                                    </p>
                                )}
                            </div>

                            <div className="col-span-1 flex items-center justify-end">
                                {data.details.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeDetail(index)}
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {errors.details && (
                    <p className="px-6 pb-4 text-sm text-destructive">
                        {errors.details}
                    </p>
                )}
            </div>

            <div className="flex gap-3">
                <Button type="submit" disabled={processing}>
                    {processing ? 'Guardando…' : submitLabel}
                </Button>
            </div>
        </form>
    );
}
