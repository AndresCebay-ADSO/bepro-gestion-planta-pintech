import { Plus } from 'lucide-react';
import type { FormEvent } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { FormLineItem } from '@/components/form-line-item';
import InputError from '@/components/input-error';
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
import { withDecimalPayloads } from '@/lib/numeric-input';

type RawMaterial = { id: number; code: string };
type ProductOption = { id: number; name: string };
type UnitOption = { id: number; name: string; symbol: string };

export type DetailRow = {
    raw_material_id: string;
    quantity: string;
    unit_of_measure_id: string;
};

export type FormulaFormData = {
    product_id: string;
    name: string;
    notes: string;
    details: DetailRow[];
};

/** Lo que se envía al crear o editar: la cantidad de cada ingrediente con punto decimal. */
export function formulaPayload<T extends FormulaFormData>(data: T): T {
    return {
        ...data,
        details: data.details.map((detail) =>
            withDecimalPayloads(detail, ['quantity']),
        ),
    };
}

type FormulaFormProps = {
    data: FormulaFormData;
    errors: Partial<Record<string, string>>;
    processing: boolean;
    products: ProductOption[];
    rawMaterials: RawMaterial[];
    units: UnitOption[];
    submitLabel: string;
    /** «Cancelar»: vuelve a la pantalla de donde se llegó. */
    cancelHref: string;
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
    cancelHref,
    heading,
    description,
    onSubmit,
    setData,
    lockProduct = false,
}: FormulaFormProps) {
    const productOptions = products.map((product) => ({
        id: String(product.id),
        label: product.name,
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
                currentIndex === index ? { ...detail, [field]: value } : detail,
            ),
        );
    };

    return (
        <form noValidate onSubmit={onSubmit} className="space-y-6">
            <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                <h2 className="font-medium text-foreground">{heading}</h2>

                <div className="grid gap-4 md:grid-cols-2">
                    <FormField
                        id="product_id"
                        label="Producto"
                        required
                        error={errors.product_id}
                        help={
                            lockProduct
                                ? 'El producto no se puede cambiar al editar una fórmula existente.'
                                : undefined
                        }
                    >
                        <Combobox
                            options={productOptions}
                            value={data.product_id}
                            onChange={(value) =>
                                setData('product_id', String(value))
                            }
                            placeholder="Busca o selecciona el producto..."
                            disabled={lockProduct}
                        />
                    </FormField>

                    <FormField
                        id="name"
                        label="Nombre"
                        error={errors.name}
                        help="Opcional. Distingue las fórmulas activas de un mismo producto al crear una orden de producción."
                    >
                        <Input
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            maxLength={60}
                            placeholder="Ej: RAL 7035"
                        />
                    </FormField>

                    <FormField
                        id="notes"
                        label="Notas / Observaciones"
                        error={errors.notes}
                        className="md:col-span-2"
                    >
                        <Textarea
                            value={data.notes}
                            onChange={(event) =>
                                setData('notes', event.target.value)
                            }
                            placeholder="Ej: Fórmula para tono azul celeste estándar, sin modificaciones..."
                            className="min-h-[80px]"
                        />
                    </FormField>
                </div>
            </div>

            <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
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
                        className="self-start sm:self-auto"
                    >
                        <Plus className="mr-1 h-4 w-4" />
                        Agregar ingrediente
                    </Button>
                </div>

                <div className="space-y-3">
                    {data.details.map((detail, index) => (
                        <FormLineItem
                            key={index}
                            index={index}
                            label="Paso"
                            labelsOnFirstRowOnly
                            onRemove={
                                data.details.length > 1
                                    ? () => removeDetail(index)
                                    : undefined
                            }
                            className="md:grid-cols-12"
                        >
                            {/* En el celular el número va en el encabezado del bloque. */}
                            <div className="hidden content-start gap-2 md:col-span-1 md:grid">
                                <Label>Paso</Label>
                                <p className="flex h-9 items-center justify-center text-sm font-medium text-muted-foreground tabular-nums">
                                    {index + 1}
                                </p>
                            </div>

                            <FormField
                                id={`details.${index}.raw_material_id`}
                                label="Materia prima"
                                required
                                error={
                                    errors[`details.${index}.raw_material_id`]
                                }
                                className="md:col-span-5"
                            >
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
                            </FormField>

                            <FormField
                                id={`details.${index}.quantity`}
                                label="Cantidad (por galón)"
                                required
                                error={errors[`details.${index}.quantity`]}
                                className="md:col-span-3"
                            >
                                <DecimalInput
                                    value={detail.quantity}
                                    onValueChange={(value) =>
                                        updateDetail(index, 'quantity', value)
                                    }
                                    placeholder="Ej: 1,5"
                                />
                            </FormField>

                            <FormField
                                id={`details.${index}.unit_of_measure_id`}
                                label="Unidad"
                                required
                                error={
                                    errors[
                                        `details.${index}.unit_of_measure_id`
                                    ]
                                }
                                className="md:col-span-3"
                            >
                                {(control) => (
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
                                        <SelectTrigger {...control}>
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
                                )}
                            </FormField>
                        </FormLineItem>
                    ))}
                </div>

                <InputError message={errors.details} />
            </div>

            <FormActions
                submitLabel={submitLabel}
                processing={processing}
                cancelHref={cancelHref}
            />
        </form>
    );
}
