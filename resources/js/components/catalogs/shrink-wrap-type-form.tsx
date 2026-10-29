import type { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import { DecimalInput } from '@/components/decimal-input';
import { FormCheckbox, FormError, FormField } from '@/components/form-field';
import { FormLineItem } from '@/components/form-line-item';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { clearListErrors } from '@/lib/form-errors';
import { withDecimalPayloads } from '@/lib/numeric-input';
import type {
    ShrinkWrapRawMaterialOption,
    ShrinkWrapTypeFormData,
} from '@/types';

// Clave estable de cada fila de la receta: con el índice, al quitar una fila del medio el estado interno del Combobox
// (texto de búsqueda) pasaría a la fila siguiente.
let rowKeySequence = 0;
const nextRowKey = () => ++rowKeySequence;

/** Lo que se envía: la cantidad de cada fila con punto decimal (se escribe con coma o punto). */
export function shrinkWrapTypePayload(
    data: ShrinkWrapTypeFormData,
): ShrinkWrapTypeFormData {
    return {
        ...data,
        items: data.items.map((item) =>
            withDecimalPayloads(item, ['quantity']),
        ),
    };
}

type Props = {
    form: ReturnType<typeof useForm<ShrinkWrapTypeFormData>>;
    rawMaterialOptions: ShrinkWrapRawMaterialOption[];
};

export function ShrinkWrapTypeFields({ form, rawMaterialOptions }: Props) {
    // Los errores de la receta llegan por línea (`items.0.quantity`), que el tipo de useForm no conoce.
    const errors = form.errors as Record<string, string | undefined>;
    const [rowKeys, setRowKeys] = useState<number[]>(() =>
        form.data.items.map(() => nextRowKey()),
    );
    const comboboxOptions = rawMaterialOptions.map((option) => ({
        id: option.value,
        label: option.is_active ? option.label : `${option.label} (inactiva)`,
    }));

    const optionOf = (rawMaterialId: string) =>
        rawMaterialOptions.find(
            (option) => String(option.value) === rawMaterialId,
        );

    const updateItem = (
        index: number,
        field: 'raw_material_id' | 'quantity',
        value: string,
    ) =>
        form.setData(
            'items',
            form.data.items.map((item, current) =>
                current === index ? { ...item, [field]: value } : item,
            ),
        );

    const addItem = () => {
        setRowKeys((keys) => [...keys, nextRowKey()]);
        form.setData('items', [
            ...form.data.items,
            { raw_material_id: '', quantity: '1' },
        ]);
    };

    const removeItem = (index: number) => {
        clearListErrors(errors, form.clearErrors, 'items');
        setRowKeys((keys) => keys.filter((_, current) => current !== index));
        form.setData(
            'items',
            form.data.items.filter((_, current) => current !== index),
        );
    };

    return (
        <div className="grid gap-6">
            <FormField
                id="name"
                label="Nombre"
                help="Lo que el operario elige al registrar un termoencogido."
                error={form.errors.name}
            >
                <Input
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    placeholder="Galón"
                />
            </FormField>

            <div className="space-y-4 rounded-lg border border-border p-4">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-sm font-medium text-foreground">
                            Consumo por aplicación
                        </h2>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            Bandejas, bolsas y demás empaque secundario que se
                            gasta cada vez que se termoencoge.
                        </p>
                    </div>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addItem}
                    >
                        <Plus className="mr-1 h-4 w-4" />
                        Agregar materia prima
                    </Button>
                </div>

                {rawMaterialOptions.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        No hay materias primas de tipo «Empaque secundario».
                        Créalas primero en una categoría de ese tipo.
                    </p>
                )}

                <div className="space-y-3">
                    {form.data.items.map((item, index) => {
                        const option = optionOf(item.raw_material_id);

                        return (
                            <FormLineItem
                                key={rowKeys[index] ?? index}
                                index={index}
                                labelsOnFirstRowOnly
                                onRemove={
                                    form.data.items.length > 1
                                        ? () => removeItem(index)
                                        : undefined
                                }
                                className="md:grid-cols-12"
                            >
                                <FormField
                                    id={`items.${index}.raw_material_id`}
                                    label="Materia prima"
                                    help={
                                        option?.is_active === false && (
                                            <span className="text-warning-text">
                                                Materia prima desactivada: se
                                                conserva en la receta, pero
                                                conviene reemplazarla.
                                            </span>
                                        )
                                    }
                                    error={
                                        errors[`items.${index}.raw_material_id`]
                                    }
                                    className="md:col-span-8"
                                >
                                    <Combobox
                                        options={comboboxOptions}
                                        value={item.raw_material_id}
                                        onChange={(value) =>
                                            updateItem(
                                                index,
                                                'raw_material_id',
                                                String(value),
                                            )
                                        }
                                        placeholder="Materia prima..."
                                    />
                                </FormField>

                                <FormField
                                    id={`items.${index}.quantity`}
                                    label="Cantidad por aplicación"
                                    error={errors[`items.${index}.quantity`]}
                                    className="md:col-span-4"
                                >
                                    {(control) => (
                                        <div className="flex items-start gap-2">
                                            <DecimalInput
                                                {...control}
                                                containerClassName="min-w-0 flex-1"
                                                value={item.quantity}
                                                onValueChange={(value) =>
                                                    updateItem(
                                                        index,
                                                        'quantity',
                                                        value,
                                                    )
                                                }
                                                placeholder="Ej: 1"
                                            />
                                            <span className="w-10 text-sm leading-9 text-muted-foreground">
                                                {option?.unit_symbol ?? ''}
                                            </span>
                                        </div>
                                    )}
                                </FormField>
                            </FormLineItem>
                        );
                    })}
                </div>

                <FormError id="items" message={errors.items} />
            </div>

            <FormCheckbox
                id="is_active"
                label="Tipo activo"
                help={
                    form.data.is_active
                        ? undefined
                        : 'Inactivo, no se podrá elegir al registrar termoencogidos nuevos. Los registros que ya lo usan no cambian.'
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
