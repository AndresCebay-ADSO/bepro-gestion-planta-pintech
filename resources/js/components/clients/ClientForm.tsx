import { LoaderCircle } from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

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
}

export default function ClientForm({
    data,
    setData,
    errors,
    processing,
    onSubmit,
    submitLabel,
}: ClientFormProps) {
    return (
        <form onSubmit={onSubmit} className="space-y-6">
            <Card>
                <CardHeader>
                    <div className="flex items-center gap-2">
                        <div className="h-5 w-5 rounded bg-muted-foreground/20" />
                        <CardTitle>Información del Cliente</CardTitle>
                    </div>
                    <CardDescription>
                        Ingresa los datos del cliente.
                    </CardDescription>
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2">
                            <Label htmlFor="business_name">
                                Razón social{' '}
                                <span className="text-destructive">*</span>
                            </Label>
                            <Input
                                id="business_name"
                                value={data.business_name}
                                onChange={(e) =>
                                    setData('business_name', e.target.value)
                                }
                                placeholder="Razón social o nombre completo"
                            />
                            {errors.business_name && (
                                <p className="text-sm text-destructive">
                                    {errors.business_name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="nit">NIT</Label>
                            <Input
                                id="nit"
                                value={data.nit}
                                onChange={(e) => setData('nit', e.target.value)}
                                placeholder="NIT (opcional)"
                            />
                            {errors.nit && (
                                <p className="text-sm text-destructive">
                                    {errors.nit}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="contact_name">
                                Nombre de contacto
                            </Label>
                            <Input
                                id="contact_name"
                                value={data.contact_name}
                                onChange={(e) =>
                                    setData('contact_name', e.target.value)
                                }
                                placeholder="Nombre de la persona de contacto"
                            />
                            {errors.contact_name && (
                                <p className="text-sm text-destructive">
                                    {errors.contact_name}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="phone">Teléfono</Label>
                            <Input
                                id="phone"
                                value={data.phone}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                placeholder="Teléfono de contacto"
                            />
                            {errors.phone && (
                                <p className="text-sm text-destructive">
                                    {errors.phone}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="shipping_address">Dirección</Label>
                        <Textarea
                            id="shipping_address"
                            value={data.shipping_address}
                            onChange={(e) =>
                                setData('shipping_address', e.target.value)
                            }
                            placeholder="Calle, número, colonia, ciudad, etc."
                        />
                        {errors.shipping_address && (
                            <p className="text-sm text-destructive">
                                {errors.shipping_address}
                            </p>
                        )}
                    </div>
                </CardContent>
            </Card>

            <div className="flex justify-end gap-3">
                <Button type="submit" disabled={processing}>
                    {processing && (
                        <LoaderCircle className="mr-2 h-4 w-4 animate-spin" />
                    )}
                    {submitLabel}
                </Button>
            </div>
        </form>
    );
}
