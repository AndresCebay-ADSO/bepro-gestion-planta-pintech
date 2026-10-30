import { Head, useForm, usePage } from '@inertiajs/react';

import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { FormPage } from '@/components/form-page';
import { FormattedNumber } from '@/components/formatted-number';
import { IntegerInput } from '@/components/integer-input';
import { PageHeader } from '@/components/page-header';
import { PlantDateInput } from '@/components/plant-date-input';
import { Combobox } from '@/components/ui/combobox';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { useSelectionDateMin } from '@/hooks/use-selection-date-min';
import { isValidIntegerText } from '@/lib/numeric-input';
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
};

export default function ShrinkWrapsCreate({
    orderOptions,
    typeOptions,
}: Props) {
    // Hoy en la planta (prop compartido): la fecha por defecto y la máxima.
    const { plantToday: today } = usePage().props;
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
            `Lote ${order.lot_number}`,
            order.product_name,
        ]
            .filter(Boolean)
            .join(' · '),
        description: order.warehouse_name ?? undefined,
    }));

    const selectedOrder = orderOptions.find(
        (order) => String(order.value) === form.data.production_order_id,
    );
    // No se termoencoge antes de que la OP se completara (lo valida el servidor): el calendario no ofrece esos días.
    const wrappedAtMin = useSelectionDateMin({
        selectedDate: selectedOrder?.completion_date,
        value: form.data.wrapped_at,
        clear: () => form.setData('wrapped_at', ''),
    });
    const selectedType = typeOptions.find(
        (type) => String(type.value) === form.data.shrink_wrap_type_id,
    );

    return (
        <>
            <Head title="Registrar termoencogido" />

            <FormPage width="narrow">
                <PageHeader
                    title="Registrar termoencogido"
                    description="Al guardar se descuenta de la bodega de la orden el empaque secundario que gastó. El registro no se edita: un error se corrige con un movimiento opuesto."
                />

                <form
                    noValidate
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(shrinkWrapsStore().url);
                    }}
                    className="grid gap-6"
                >
                    <FormField
                        id="production_order_id"
                        label="Orden de producción"
                        error={form.errors.production_order_id}
                        help={
                            <>
                                Solo órdenes completadas: antes no hay lotes que
                                termoencoger.
                                {selectedOrder?.warehouse_name &&
                                    ` Se descuenta de ${selectedOrder.warehouse_name}.`}
                            </>
                        }
                    >
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
                    </FormField>

                    <FormField
                        id="shrink_wrap_type_id"
                        label="Tipo"
                        error={form.errors.shrink_wrap_type_id}
                        help={
                            typeOptions.length === 0
                                ? 'No hay tipos activos. El Admin los crea en Configuración → Catálogos.'
                                : undefined
                        }
                    >
                        {(control) => (
                            <Select
                                value={form.data.shrink_wrap_type_id}
                                onValueChange={(value) =>
                                    form.setData('shrink_wrap_type_id', value)
                                }
                            >
                                <SelectTrigger {...control}>
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
                        )}
                    </FormField>

                    <div className="grid gap-5 sm:grid-cols-2">
                        {/* Cuántas veces se termoencogió: solo enteros. */}
                        <FormField
                            id="applications"
                            label="Aplicaciones"
                            error={form.errors.applications}
                        >
                            <IntegerInput
                                value={form.data.applications}
                                onValueChange={(value) =>
                                    form.setData('applications', value)
                                }
                                placeholder="Ej: 20"
                            />
                        </FormField>

                        <FormField
                            id="wrapped_at"
                            label="Fecha"
                            error={form.errors.wrapped_at}
                        >
                            <PlantDateInput
                                notFuture
                                min={wrappedAtMin}
                                value={form.data.wrapped_at}
                                onChange={(value) =>
                                    form.setData('wrapped_at', value)
                                }
                            />
                        </FormField>
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
                            {/* Solo con un entero: con «3,5» el campo ya marca el error. */}
                            {form.data.applications !== '' &&
                                isValidIntegerText(form.data.applications) && (
                                    <p className="mt-2 text-sm font-medium text-foreground">
                                        × {form.data.applications} aplicaciones
                                    </p>
                                )}
                        </div>
                    )}

                    <FormField
                        id="notes"
                        label="Notas"
                        error={form.errors.notes}
                    >
                        <Textarea
                            value={form.data.notes}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            maxLength={1000}
                            rows={2}
                        />
                    </FormField>

                    <FormActions
                        submitLabel="Registrar"
                        processing={form.processing}
                        cancelHref={shrinkWrapsIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
}

// Las migas son la única forma de volver al listado (DESIGN.md §10).
ShrinkWrapsCreate.layout = {
    breadcrumbs: [
        { title: 'Termoencogido', href: shrinkWrapsIndex() },
        { title: 'Registrar termoencogido', href: shrinkWrapsCreate() },
    ],
};
