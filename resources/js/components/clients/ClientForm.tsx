import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { index as clientsIndex } from '@/routes/clients';

interface ClientFormData {
    business_name: string;
    nit: string;
    contact_name: string;
    phone: string;
    shipping_address: string;
}

interface ClientFormProps {
    data: ClientFormData;
    setData: (field: string, value: string) => void;
    errors: Record<string, string>;
    processing: boolean;
    onSubmit: (e: React.FormEvent<HTMLFormElement>) => void;
    submitLabel: string;
    /** Campos adicionales al final de la tarjeta (p. ej., "Cliente activo" al editar). */
    children?: React.ReactNode;
}

export default function ClientForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
    children,
}: ClientFormProps) {
    return (
        <form noValidate onSubmit={onSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <CardTitle>Información del cliente</CardTitle>
                    <CardDescription>
                        Ingresa los datos del cliente.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-5">
                    <div className="grid gap-5 md:grid-cols-2">
                        <FormField
                            id="business_name"
                            label="Razón social"
                            required
                            error={errors.business_name}
                        >
                            <Input
                                value={data.business_name}
                                onChange={(e) =>
                                    setData('business_name', e.target.value)
                                }
                                placeholder="Razón social o nombre completo"
                            />
                        </FormField>

                        <FormField id="nit" label="NIT" error={errors.nit}>
                            <Input
                                value={data.nit}
                                onChange={(e) => setData('nit', e.target.value)}
                                placeholder="NIT (opcional)"
                            />
                        </FormField>

                        <FormField
                            id="contact_name"
                            label="Nombre de contacto"
                            error={errors.contact_name}
                        >
                            <Input
                                value={data.contact_name}
                                onChange={(e) =>
                                    setData('contact_name', e.target.value)
                                }
                                placeholder="Nombre de la persona de contacto"
                            />
                        </FormField>

                        <FormField
                            id="phone"
                            label="Teléfono"
                            error={errors.phone}
                        >
                            <Input
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                placeholder="Teléfono de contacto"
                            />
                        </FormField>

                        <FormField
                            id="shipping_address"
                            label="Dirección"
                            error={errors.shipping_address}
                            className="md:col-span-2"
                        >
                            <Textarea
                                maxLength={255}
                                value={data.shipping_address}
                                onChange={(e) =>
                                    setData('shipping_address', e.target.value)
                                }
                                placeholder="Calle, número, colonia, ciudad, etc."
                            />
                        </FormField>
                    </div>

                    {children}
                </CardContent>
            </Card>

            <FormActions
                submitLabel={submitLabel}
                processing={processing}
                cancelHref={clientsIndex().url}
            />
        </form>
    );
}
