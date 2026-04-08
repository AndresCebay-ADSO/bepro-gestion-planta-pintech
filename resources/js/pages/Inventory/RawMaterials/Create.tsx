import { Head, Link, useForm } from '@inertiajs/react';
import { route } from 'ziggy-js';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type UnitOption = {
    id: number;
    name: string;
    symbol: string;
};

type Props = {
    units: UnitOption[];
};

type RawMaterialForm = {
    name: string;
    unit_of_measure_id: string;
    current_price: string;
    previous_price: string;
    minimum_stock: string;
    alert_days_before_expiry: string;
    is_active: boolean;
};

export default function RawMaterialsCreate({ units }: Props) {
    const form = useForm<RawMaterialForm>({
        name: '',
        unit_of_measure_id: '',
        current_price: '',
        previous_price: '',
        minimum_stock: '0',
        alert_days_before_expiry: '30',
        is_active: true,
    });

    const submit = () => {
        form.transform((data) => ({
            ...data,
            unit_of_measure_id: Number(data.unit_of_measure_id),
            previous_price: data.previous_price === '' ? null : data.previous_price,
        }));

        form.post(route('raw-materials.store'));
    };

    return (
        <>
            <Head title="Nueva Materia Prima" />

            <div className="mx-auto max-w-3xl space-y-6 p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-semibold text-foreground">Nueva Materia Prima</h1>
                    <p className="text-sm text-muted-foreground">Registra una nueva materia prima en el catálogo de inventario.</p>
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
                            <Input id="name" value={form.data.name} onChange={(event) => form.setData('name', event.target.value)} maxLength={150} />
                            <InputError message={form.errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="unit">Unidad de medida</Label>
                            <Select value={form.data.unit_of_measure_id} onValueChange={(value) => form.setData('unit_of_measure_id', value)}>
                                <SelectTrigger id="unit" className="w-full">
                                    <SelectValue placeholder="Seleccionar unidad" />
                                </SelectTrigger>
                                <SelectContent>
                                    {units.map((unit) => (
                                        <SelectItem key={unit.id} value={String(unit.id)}>
                                            {unit.name} ({unit.symbol})
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={form.errors.unit_of_measure_id} />
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="current_price">Precio actual</Label>
                                <Input id="current_price" type="number" min="0" step="0.0001" value={form.data.current_price} onChange={(event) => form.setData('current_price', event.target.value)} />
                                <InputError message={form.errors.current_price} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="previous_price">Precio anterior (opcional)</Label>
                                <Input id="previous_price" type="number" min="0" step="0.0001" value={form.data.previous_price} onChange={(event) => form.setData('previous_price', event.target.value)} />
                                <InputError message={form.errors.previous_price} />
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="grid gap-2">
                                <Label htmlFor="minimum_stock">Stock mínimo</Label>
                                <Input id="minimum_stock" type="number" min="0" step="0.0001" value={form.data.minimum_stock} onChange={(event) => form.setData('minimum_stock', event.target.value)} />
                                <InputError message={form.errors.minimum_stock} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="alert_days_before_expiry">Días de alerta por vencimiento</Label>
                                <Input id="alert_days_before_expiry" type="number" min="1" step="1" value={form.data.alert_days_before_expiry} onChange={(event) => form.setData('alert_days_before_expiry', event.target.value)} />
                                <InputError message={form.errors.alert_days_before_expiry} />
                            </div>
                        </div>

                        <div className="flex items-center gap-3 rounded-md border border-border px-3 py-2">
                            <Checkbox
                                id="is_active"
                                checked={form.data.is_active}
                                onCheckedChange={(checked) => form.setData('is_active', checked === true)}
                            />
                            <Label htmlFor="is_active" className="cursor-pointer">Materia prima activa</Label>
                        </div>

                        <div className="flex flex-col gap-2 pt-2 sm:flex-row">
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Guardando...' : 'Guardar'}
                            </Button>
                            <Button type="button" variant="outline" asChild>
                                <Link href={route('raw-materials.index')}>Cancelar</Link>
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </>
    );
}
