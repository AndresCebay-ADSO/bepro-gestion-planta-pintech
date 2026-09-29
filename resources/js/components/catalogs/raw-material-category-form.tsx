import type { useForm } from '@inertiajs/react';
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
                <div className="grid gap-2">
                    <Label htmlFor="code">Código</Label>
                    <Input
                        id="code"
                        value={form.data.code}
                        onChange={(event) =>
                            form.setData('code', event.target.value)
                        }
                        maxLength={50}
                        placeholder="RESINAS"
                        autoComplete="off"
                    />
                    <p className="text-xs text-muted-foreground">
                        Identificador corto; se guarda en mayúsculas.
                    </p>
                    <InputError message={form.errors.code} />
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
                        placeholder="Resinas"
                    />
                    <InputError message={form.errors.name} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="type">Tipo de insumo</Label>
                <Select
                    value={form.data.type}
                    onValueChange={(value: RawMaterialType) =>
                        form.setData('type', value)
                    }
                    disabled={typeLocked}
                >
                    <SelectTrigger id="type">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        {typeOptions.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <p className="text-xs text-muted-foreground">
                    {typeLocked
                        ? rawMaterialsCount === 1
                            ? 'No se puede cambiar: la categoría tiene 1 materia prima. Muévela a otra categoría primero.'
                            : `No se puede cambiar: la categoría tiene ${rawMaterialsCount} materias primas. Muévelas a otra categoría primero.`
                        : RAW_MATERIAL_TYPE_HINTS[form.data.type]}
                </p>
                <InputError message={form.errors.type} />
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
                        Categoría activa
                    </Label>
                </div>
                {!form.data.is_active && (
                    <p className="text-xs text-muted-foreground">
                        Inactiva, no se podrá elegir al registrar materias
                        primas nuevas. Las que ya la tienen la conservan.
                    </p>
                )}
                <InputError message={form.errors.is_active} />
            </div>
        </div>
    );
}
