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

export type WarehouseFormData = {
    name: string;
    city: string;
    address: string;
    type: 'factory' | 'storage';
    is_active: boolean;
};

type Props = {
    form: ReturnType<typeof useForm<WarehouseFormData>>;
    /**
     * Al editar, el tipo no se cambia (el servidor no lo acepta): una fábrica con materia prima pasada a almacén dejaría
     * MP donde no puede estar. Se muestra bloqueado, con el motivo.
     */
    lockType?: boolean;
};

/** Los campos de una bodega: los mismos al crearla y al editarla. */
export function WarehouseFields({ form, lockType = false }: Props) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-5 md:grid-cols-2">
                <FormField
                    id="name"
                    label="Nombre"
                    required
                    error={form.errors.name}
                >
                    <Input
                        value={form.data.name}
                        onChange={(event) =>
                            form.setData('name', event.target.value)
                        }
                        maxLength={100}
                    />
                </FormField>

                <FormField
                    id="type"
                    label="Tipo de bodega"
                    required={!lockType}
                    help={
                        lockType
                            ? 'No se puede cambiar después de crear la bodega.'
                            : undefined
                    }
                    error={form.errors.type}
                >
                    {(control) => (
                        <Select
                            disabled={lockType}
                            value={form.data.type}
                            onValueChange={(value: 'factory' | 'storage') =>
                                form.setData('type', value)
                            }
                        >
                            <SelectTrigger {...control}>
                                <SelectValue placeholder="Seleccione el tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="factory">Fábrica</SelectItem>
                                <SelectItem value="storage">
                                    Bodega / Punto de Venta
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    )}
                </FormField>

                <FormField
                    id="city"
                    label="Ciudad"
                    required
                    error={form.errors.city}
                >
                    <Input
                        value={form.data.city}
                        onChange={(event) =>
                            form.setData('city', event.target.value)
                        }
                        maxLength={100}
                    />
                </FormField>

                <FormField
                    id="address"
                    label="Dirección"
                    error={form.errors.address}
                >
                    <Input
                        value={form.data.address}
                        onChange={(event) =>
                            form.setData('address', event.target.value)
                        }
                        maxLength={255}
                    />
                </FormField>
            </div>

            <FormCheckbox
                id="is_active"
                label="Bodega activa"
                error={form.errors.is_active}
                checked={form.data.is_active}
                onCheckedChange={(checked) =>
                    form.setData('is_active', checked === true)
                }
            />
        </div>
    );
}
