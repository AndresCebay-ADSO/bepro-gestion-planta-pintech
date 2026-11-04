import type { useForm } from '@inertiajs/react';
import { FormCheckbox, FormField } from '@/components/form-field';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import type { SupplierFormData } from '@/types';

type Props = {
    form: ReturnType<typeof useForm<SupplierFormData>>;
};

export function SupplierFields({ form }: Props) {
    return (
        <div className="grid gap-5">
            {/* Por pares, como el formulario de clientes: quién es y a quién se llama. */}
            <div className="grid gap-5 sm:grid-cols-2">
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
                        maxLength={150}
                        placeholder="Ej: Químicos del Valle S.A.S."
                    />
                </FormField>

                <FormField id="nit" label="NIT" error={form.errors.nit}>
                    <Input
                        value={form.data.nit}
                        onChange={(event) =>
                            form.setData('nit', event.target.value)
                        }
                        maxLength={20}
                        placeholder="Ej: 900123456-7"
                        autoComplete="off"
                    />
                </FormField>

                <FormField
                    id="contact_name"
                    label="Nombre de contacto"
                    error={form.errors.contact_name}
                >
                    <Input
                        value={form.data.contact_name}
                        onChange={(event) =>
                            form.setData('contact_name', event.target.value)
                        }
                        maxLength={255}
                        placeholder="Ej: Juan Pérez"
                        autoComplete="off"
                    />
                </FormField>

                <FormField
                    id="phone"
                    label="Teléfono"
                    error={form.errors.phone}
                >
                    <Input
                        type="tel"
                        value={form.data.phone}
                        onChange={(event) =>
                            form.setData('phone', event.target.value)
                        }
                        maxLength={20}
                        placeholder="Ej: 3001234567"
                        autoComplete="off"
                    />
                </FormField>

                <FormField
                    id="email"
                    label="Correo electrónico"
                    error={form.errors.email}
                    className="sm:col-span-2"
                >
                    <Input
                        type="email"
                        value={form.data.email}
                        onChange={(event) =>
                            form.setData('email', event.target.value)
                        }
                        maxLength={255}
                        placeholder="ventas@proveedor.com"
                        autoComplete="off"
                    />
                </FormField>
            </div>

            <FormField id="notes" label="Notas" error={form.errors.notes}>
                <Textarea
                    value={form.data.notes}
                    onChange={(event) =>
                        form.setData('notes', event.target.value)
                    }
                    maxLength={2000}
                    rows={2}
                />
            </FormField>

            <FormCheckbox
                id="is_active"
                label="Proveedor activo"
                help={
                    form.data.is_active
                        ? undefined
                        : 'Inactivo, no se podrá elegir en las entradas nuevas. Los lotes que ya lo tienen lo conservan.'
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
