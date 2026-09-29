import type { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { ProductCategoryFormData } from '@/types';

type Props = {
    form: ReturnType<typeof useForm<ProductCategoryFormData>>;
};

export function ProductCategoryFields({ form }: Props) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                    id="name"
                    value={form.data.name}
                    onChange={(event) =>
                        form.setData('name', event.target.value)
                    }
                    maxLength={100}
                    placeholder="Esmaltes alquídicos"
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
                        Inactiva, no se podrá elegir al registrar productos
                        nuevos. Los que ya la tienen la conservan.
                    </p>
                )}
                <InputError message={form.errors.is_active} />
            </div>
        </div>
    );
}
