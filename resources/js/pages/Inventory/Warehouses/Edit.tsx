import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type Props = {
    warehouse: {
        id: number;
        name: string;
        city: string;
        address: string | null;
        type: 'fabrica' | 'bodega';
        is_active: boolean;
    };
};

type WarehouseForm = {
    name: string;
    city: string;
    address: string;
    type: 'fabrica' | 'bodega';
    is_active: boolean;
};

export default function WarehousesEdit({ warehouse }: Props) {
    const form = useForm<WarehouseForm>({
        name: warehouse.name,
        city: warehouse.city,
        address: warehouse.address ?? '',
        type: warehouse.type,
        is_active: warehouse.is_active,
    });

    const submit = () => {
        form.put(route('warehouses.update', warehouse.id));
    };

    return (
        <>
            <Head title={`Editar ${warehouse.name}`} />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Editar Bodega</h1>
                    <p className="text-sm text-muted-foreground">Actualiza la información de {warehouse.name}.</p>
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                        className="grid gap-5"
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="name">Nombre</Label>
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength={100} />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="city">Ciudad</Label>
                            <Input id="city" value={form.data.city} onChange={(event) => form.setData('city', event.target.value)} maxLength={100} />
                            <InputError message={form.errors.city} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="address">Dirección</Label>
                            <Input
                                id="address"
                                value={form.data.address}
                                onChange={(event) => form.setData('address', event.target.value)}
                                maxLength={255}
                            />
                            <InputError message={form.errors.address} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="type">Tipo de Bodega</Label>
                            <Select
                                value={form.data.type}
                                onValueChange={(value: 'fabrica' | 'bodega') => form.setData('type', value)}
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Seleccione el tipo" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="fabrica">Fábrica (Producción)</SelectItem>
                                    <SelectItem value="bodega">Bodega (Venta / Almacenamiento)</SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.type} />
                        </div>

                        <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData('is_active', checked === true)}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">Bodega activa</Label>
                        </div>

                        <div className="flex flex-col gap-2 pt-2 sm:flex-row">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Actualizando...' : 'Guardar cambios'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('warehouses.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}

