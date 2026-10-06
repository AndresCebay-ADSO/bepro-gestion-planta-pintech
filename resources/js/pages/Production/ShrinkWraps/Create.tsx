import { Head, Link, useForm } from '@inertiajs/react';

import { DetailPageHeader } from '@/components/detail-page-header';
import { FormattedNumber } from '@/components/formatted-number';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
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
import {
    create as shrinkWrapsCreate,
    index as shrinkWrapsIndex,
    store as shrinkWrapsStore,
} from '@/routes/production/shrink-wraps';
import type {
    ShrinkWrapFormData,
    ShrinkWrapOrderOption,
    ShrinkWrapTypeOption,
} from '@/types';

type Props = {
    orderOptions: ShrinkWrapOrderOption[];
    typeOptions: ShrinkWrapTypeOption[];
    /** Hoy en la planta: la fecha por defecto y la máxima. */
    today: string;
};

export default function ShrinkWrapsCreate({
    orderOptions,
    typeOptions,
    today,
}: Props) {
    const form = useForm<ShrinkWrapFormData>({
        production_order_id: '',
        shrink_wrap_type_id: '',
        applications: '',
        wrapped_at: today,
        notes: '',
    });

    const orderComboboxOptions = orderOptions.map((order) => ({
        id: order.value,
        label: [
            order.order_number,
            order.lot_number !== null ? `Lote ${order.lot_number}` : null,
            order.product_name,
        ]
            .filter(Boolean)
            .join(' · '),
        description: order.warehouse_name ?? undefined,
    }));

    const selectedOrder = orderOptions.find(
        (order) => String(order.value) === form.data.production_order_id,
    );
    const selectedType = typeOptions.find(
        (type) => String(type.value) === form.data.shrink_wrap_type_id,
    );

    return (
        <>
            <Head title="Registrar termoencogido" />

            <div className="space-y-6 p-6">
                <DetailPageHeader
                    breadcrumbs={[
                        {
                            title: 'Termoencogido',
                            href: shrinkWrapsIndex().url,
                        },
                        { title: 'Registrar', href: shrinkWrapsCreate().url },
                    ]}
                    title="Registrar termoencogido"
                    subtitle="Al guardar se descuenta de la bodega de la orden el empaque secundario que gastó. El registro no se edita: un error se corrige con un movimiento opuesto."
                    defaultReturnHref={shrinkWrapsIndex().url}
                    defaultReturnLabel="Termoencogido"
                />

                <form
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(shrinkWrapsStore().url);
                    }}
                    className="grid max-w-2xl gap-6"
                >
                    <div className="grid gap-2">
                        <Label>Orden de producción</Label>
                        <Combobox
                            options={orderComboboxOptions}
                            value={form.data.production_order_id}
                            onChange={(value) =>
                                form.setData(
                                    'production_order_id',
                                    String(value),
                                )
                            }
                            placeholder="Buscar por número, lote o producto..."
                            emptyText="No hay órdenes completadas con ese dato."
                        />
                        <p className="text-xs text-muted-foreground">
                            Solo órdenes completadas: antes no hay lotes que
                            termoencoger.
                            {selectedOrder?.warehouse_name &&
                                ` Se descuenta de ${selectedOrder.warehouse_name}.`}
                        </p>
                        <InputError message={form.errors.production_order_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="shrink_wrap_type_id">Tipo</Label>
                        <Select
                            value={form.data.shrink_wrap_type_id}
                            onValueChange={(value) =>
                                form.setData('shrink_wrap_type_id', value)
                            }
                        >
                            <SelectTrigger id="shrink_wrap_type_id">
                                <SelectValue placeholder="Elige el tipo..." />
                            </SelectTrigger>
                            <SelectContent>
                                {typeOptions.map((type) => (
                                    <SelectItem
                                        key={type.value}
                                        value={String(type.value)}
                                    >
                                        {type.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {typeOptions.length === 0 && (
                            <p className="text-xs text-muted-foreground">
                                No hay tipos activos. El Admin los crea en
                                Configuración → Catálogos.
                            </p>
                        )}
                        <InputError message={form.errors.shrink_wrap_type_id} />
                    </div>

                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="applications">Aplicaciones</Label>
                            <Input
                                id="applications"
                                inputMode="numeric"
                                value={form.data.applications}
                                onChange={(event) => {
                                    const value = event.target.value;

                                    // Cuántas veces se termoencogió: solo enteros.
                                    if (/^\d{0,8}$/.test(value)) {
                                        form.setData('applications', value);
                                    }
                                }}
                                placeholder="Ej: 20"
                            />
                            <InputError message={form.errors.applications} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="wrapped_at">Fecha</Label>
                            <Input
                                id="wrapped_at"
                                type="date"
                                max={today}
                                min={
                                    selectedOrder?.completion_date ?? undefined
                                }
                                value={form.data.wrapped_at}
                                onChange={(event) =>
                                    form.setData(
                                        'wrapped_at',
                                        event.target.value,
                                    )
                                }
                            />
                            <InputError message={form.errors.wrapped_at} />
                        </div>
                    </div>

                    {selectedType && (
                        <div className="rounded-lg border border-border p-4">
                            <p className="text-sm font-medium text-foreground">
                                Por cada aplicación se descuenta:
                            </p>
                            <ul className="mt-2 space-y-1 text-sm text-muted-foreground">
                                {selectedType.items.map((item) => (
                                    <li key={item.code}>
                                        <FormattedNumber
                                            value={item.quantity}
                                            maxDecimals={4}
                                        />{' '}
                                        {item.unit_symbol}{' '}
                                        <span className="font-mono text-foreground">
                                            {item.code}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                            {form.data.applications !== '' && (
                                <p className="mt-2 text-sm font-medium text-foreground">
                                    × {form.data.applications} aplicaciones
                                </p>
                            )}
                        </div>
                    )}

                    <div className="grid gap-2">
                        <Label htmlFor="notes">Notas</Label>
                        <Textarea
                            id="notes"
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            maxLength={1000}
                            rows={2}
                        />
                        <InputError message={form.errors.notes} />
                    </div>

                    <div className="flex flex-col gap-2 sm:flex-row">
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Registrando...' : 'Registrar'}
                        </Button>
                        <Button type="button" variant="outline" asChild>
                            <Link href={shrinkWrapsIndex().url}>Cancelar</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
