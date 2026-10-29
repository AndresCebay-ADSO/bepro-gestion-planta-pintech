import type { useForm } from '@inertiajs/react';
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
import { RAW_MATERIAL_TYPE_HINTS } from '@/lib/raw-material-types';
import type { RawMaterialCategoryFormData, RawMaterialType } from '@/types';

type Props = {
    form: ReturnType<typeof useForm<RawMaterialCategoryFormData>>;
    typeOptions: { value: string; label: string }[];
    /** En edición: cuántas materias primas tiene. Con alguna, el tipo no se puede cambiar. */
    rawMaterialsCount?: number;
};

export function RawMaterialCategoryFields({
    form,
    typeOptions,
    rawMaterialsCount = 0,
}: Props) {
    const typeLocked = rawMaterialsCount > 0;

    return (
        <div className="grid gap-5">
            <div className="grid gap-5 sm:grid-cols-2">
                <FormField
                    id="code"
                    label="Código"
                    help="Identificador corto; se guarda en mayúsculas."
                    error={form.errors.code}
                >
                    <Input
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        maxLength={50}
                        className="uppercase"
                        placeholder="RESINAS"
                        autoComplete="off"
                    />
                </FormField>

                <FormField id="name" label="Nombre" error={form.errors.name}>
                    <Input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={100}
                        placeholder="Resinas"
                    />
                </FormField>
            </div>

            <FormField
                id="type"
                label="Tipo de insumo"
                help={
                    typeLocked
                        ? rawMaterialsCount === 1
                            ? 'No se puede cambiar: la categoría tiene 1 materia prima. Muévela a otra categoría primero.'
                            : `No se puede cambiar: la categoría tiene ${rawMaterialsCount} materias primas. Muévelas a otra categoría primero.`
                        : RAW_MATERIAL_TYPE_HINTS[form.data.type]
                }
                error={form.errors.type}
            >
                {(control) => (
                    <Select
                        value={form.data.type}
                        onValueChange={(value: RawMaterialType) =>
                            form.setData('type', value)
                        }
                        disabled={typeLocked}
                    >
                        <SelectTrigger {...control}>
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {typeOptions.map((option) => (
                                <SelectItem
                                    key={option.value}
                                    value={option.value}
                                >
                                    {option.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                )}
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

            <FormCheckbox
                id="is_active"
                label="Categoría activa"
                help={
                    form.data.is_active
                        ? undefined
                        : 'Inactiva, no se podrá elegir al registrar materias primas nuevas. Las que ya la tienen la conservan.'
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
