import type { useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type {
    ShrinkWrapRawMaterialOption,
    ShrinkWrapTypeFormData,
} from '@/types';

// Clave estable de cada fila de la receta: con el índice, al quitar una fila del medio el estado interno del Combobox
// (texto de búsqueda) pasaría a la fila siguiente.
let rowKeySequence = 0;
const nextRowKey = () => ++rowKeySequence;

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
        setRowKeys((keys) => keys.filter((_, current) => current !== index));
        form.setData(
            'items',
            form.data.items.filter((_, current) => current !== index),
        );
    };

    return (
        <div className="grid gap-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    placeholder="Galón"
                />
                <p className="text-xs text-muted-foreground">
                    Lo que el operario elige al registrar un termoencogido.
                </p>
                <InputError message={form.errors.name} />
            </div>

            <div className="rounded-lg border border-border">
                <div className="flex flex-col gap-3 border-b border-border p-4 sm:flex-row sm:items-center sm:justify-between">
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
                    <p className="px-4 pt-4 text-sm text-muted-foreground">
                        No hay materias primas de tipo «Empaque secundario».
                        Créalas primero en una categoría de ese tipo.
                    </p>
                )}

                <div className="divide-y divide-border">
                    {form.data.items.map((item, index) => (
                        <div
                            key={rowKeys[index] ?? index}
                            className="grid grid-cols-12 items-start gap-3 p-4"
                        >
                            <div className="col-span-12 space-y-1 sm:col-span-6">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Materia prima
                                    </Label>
                                )}
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
                                {optionOf(item.raw_material_id)?.is_active ===
                                    false && (
                                    <p className="text-xs text-amber-600 dark:text-amber-400">
                                        Materia prima desactivada: se conserva
                                        en la receta, pero conviene
                                        reemplazarla.
                                    </p>
                                )}
                                <InputError
                                    message={
                                        errors[`items.${index}.raw_material_id`]
                                    }
                                />
                            </div>

                            <div className="col-span-8 space-y-1 sm:col-span-4">
                                {index === 0 && (
                                    <Label className="text-xs text-muted-foreground">
                                        Cantidad
                                    </Label>
                                )}
                                <div className="flex items-center gap-2">
                                    <Input
                                        type="text"
                                        inputMode="decimal"
                                        value={item.quantity}
                                        onChange={(event) => {
                                            const value = event.target.value;

                                            if (
                                                value === '' ||
                                                /^\d*[.,]?\d{0,4}$/.test(value)
                                            ) {
                                                updateItem(
                                                    index,
                                                    'quantity',
                                                    value,
                                                );
                                            }
                                        }}
                                        placeholder="Ej: 1"
                                        aria-label="Cantidad por aplicación"
                                    />
                                    <span className="w-10 text-sm text-muted-foreground">
                                        {optionOf(item.raw_material_id)
                                            ?.unit_symbol ?? ''}
                                    </span>
                                </div>
                                <InputError
                                    message={errors[`items.${index}.quantity`]}
                                />
                            </div>

                            <div className="col-span-4 flex justify-end sm:col-span-2">
                                {form.data.items.length > 1 && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => removeItem(index)}
                                        className={cn(
                                            'text-destructive hover:bg-destructive/10 hover:text-destructive',
                                            // Alinea con los campos de la primera línea, que llevan etiqueta.
                                            index === 0 && 'mt-5',
                                        )}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                        <span className="sr-only">
                                            Quitar materia prima
                                        </span>
                                    </Button>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                {errors.items && (
                    <p className="px-4 pb-4 text-sm text-destructive">
                        {errors.items}
                    </p>
                )}
            </div>

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
                        Tipo activo
                    </Label>
                </div>
                {!form.data.is_active && (
                    <p className="text-xs text-muted-foreground">
                        Inactivo, no se podrá elegir al registrar termoencogidos
                        nuevos. Los registros que ya lo usan no cambian.
                    </p>
                )}
                <InputError message={form.errors.is_active} />
            </div>
        </div>
    );
}
