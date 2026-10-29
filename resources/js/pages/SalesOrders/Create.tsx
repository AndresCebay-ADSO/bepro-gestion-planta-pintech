import { Head, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';

import { store as salesOrderStore } from '@/actions/App/Http/Controllers/SalesOrderController';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { FormLineItem } from '@/components/form-line-item';
import { FormPage } from '@/components/form-page';
import InputError from '@/components/input-error';
import { IntegerInput } from '@/components/integer-input';
import { PageHeader } from '@/components/page-header';
import { PlantDateInput } from '@/components/plant-date-input';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { UNITS_MAX_DIGITS } from '@/lib/number-types';
import {
    create as salesOrdersCreate,
    index as salesOrdersIndex,
} from '@/routes/sales-orders';

type ClientOption = {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
    shipping_address: string | null;
};

type VariantOption = {
    id: number;
    name: string;
    presentation_label: string | null;
    presentation_value: number | null;
};

type ProductOption = {
    id: number;
    name: string;
    variants: VariantOption[];
};

type ItemRow = {
    product_id: string;
    product_variant_id: string;
    quantity: string;
    color: string;
};

type Props = {
    clients: ClientOption[];
    products: ProductOption[];
};

export default function SalesOrdersCreate({ clients, products }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        client_id: '',
        client_business_name: '',
        client_nit: '',
        client_contact_name: '',
        client_phone: '',
        priority: 'medium',
        required_date: '',
        shipping_address: '',
        notes: '',
        items: [] as ItemRow[],
    });

    // El cliente elegido: la nota de la dirección cambia si tiene una registrada o no (D-CLI3).
    const selectedClient = clients.find((c) => String(c.id) === data.client_id);

    const clientOptions = clients.map((c) => ({
        id: String(c.id),
        label: `${c.business_name}${c.contact_name ? ` — ${c.contact_name}` : ''}`,
    }));

    const productOptions = products.map((p) => ({
        id: String(p.id),
        label: p.name,
    }));

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        // Las cantidades son unidades enteras (<IntegerInput>): se envían como se escribieron.
        post(salesOrderStore().url);
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            {
                product_id: '',
                product_variant_id: '',
                quantity: '',
                color: '',
            },
        ]);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, i) => i !== index),
        );
    };

    const updateItem = <K extends keyof ItemRow>(
        index: number,
        field: K,
        value: ItemRow[K],
    ) => {
        const updated = data.items.map((item, i) => {
            if (i !== index) {
                return item;
            }

            const next = { ...item, [field]: value };

            // Reset variant when product changes
            if (field === 'product_id') {
                next.product_variant_id = '';
            }

            return next;
        });
        setData('items', updated);
    };

    return (
        <>
            <Head title="Nuevo pedido" />

            <FormPage width="wide">
                <PageHeader title="Nuevo pedido" />

                <form noValidate onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="text-lg font-semibold">
                            Información general
                        </h2>

                        <div className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="client_id"
                                label="Cliente"
                                required
                                error={errors.client_id}
                            >
                                <Combobox
                                    options={clientOptions}
                                    value={data.client_id}
                                    onChange={(value) => {
                                        const client = clients.find(
                                            (c) => String(c.id) === value,
                                        );

                                        if (client) {
                                            setData('client_id', String(value));
                                            setData(
                                                'client_business_name',
                                                client.business_name,
                                            );
                                            setData(
                                                'client_nit',
                                                client.nit ?? '',
                                            );
                                            setData(
                                                'client_contact_name',
                                                client.contact_name ?? '',
                                            );
                                            setData(
                                                'client_phone',
                                                client.phone ?? '',
                                            );
                                            setData(
                                                'shipping_address',
                                                client.shipping_address ?? '',
                                            );
                                        } else {
                                            setData('client_id', String(value));
                                        }
                                    }}
                                    placeholder="Seleccionar cliente..."
                                />
                            </FormField>

                            <FormField
                                id="required_date"
                                label="Fecha requerida"
                                required
                                error={errors.required_date}
                            >
                                <PlantDateInput
                                    notPast
                                    value={data.required_date}
                                    onChange={(value) =>
                                        setData('required_date', value)
                                    }
                                />
                            </FormField>

                            <FormField
                                id="priority"
                                label="Prioridad"
                                required
                                error={errors.priority}
                            >
                                {(control) => (
                                    <Select
                                        value={data.priority}
                                        onValueChange={(value) =>
                                            setData('priority', value)
                                        }
                                    >
                                        <SelectTrigger {...control}>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="low">
                                                Baja
                                            </SelectItem>
                                            <SelectItem value="medium">
                                                Media
                                            </SelectItem>
                                            <SelectItem value="high">
                                                Alta
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                )}
                            </FormField>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <FormField
                                id="client_business_name"
                                label="Razón social"
                            >
                                <Input
                                    value={data.client_business_name}
                                    readOnly
                                    className="bg-muted/50"
                                />
                            </FormField>
                            <FormField id="client_nit" label="NIT">
                                <Input
                                    value={data.client_nit}
                                    readOnly
                                    className="bg-muted/50"
                                />
                            </FormField>
                            <FormField
                                id="client_contact_name"
                                label="Contacto"
                                error={errors.client_contact_name}
                            >
                                <Input
                                    value={data.client_contact_name}
                                    onChange={(e) =>
                                        setData(
                                            'client_contact_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nombre del contacto para este pedido"
                                />
                            </FormField>
                            <FormField
                                id="client_phone"
                                label="Teléfono"
                                error={errors.client_phone}
                            >
                                <Input
                                    value={data.client_phone}
                                    onChange={(e) =>
                                        setData('client_phone', e.target.value)
                                    }
                                    placeholder="Teléfono de contacto para este pedido"
                                />
                            </FormField>
                        </div>

                        <FormField
                            id="shipping_address"
                            label="Dirección de entrega"
                            required
                            error={errors.shipping_address}
                            help={
                                selectedClient
                                    ? selectedClient.shipping_address
                                        ? 'Dirección registrada del cliente. Cámbiala si este pedido va a otro lugar; la del cliente no se modifica.'
                                        : 'Este cliente no tiene dirección registrada: escribe a dónde va este pedido.'
                                    : undefined
                            }
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

                        <FormField
                            id="notes"
                            label="Notas"
                            error={errors.notes}
                        >
                            <Textarea
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Observaciones adicionales..."
                            />
                        </FormField>
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">Productos</h2>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={addItem}
                            >
                                <Plus className="mr-1 h-4 w-4" />
                                Agregar producto
                            </Button>
                        </div>

                        <InputError message={errors.items} />

                        {data.items.length === 0 ? (
                            <div className="rounded border border-dashed border-border py-8 text-center text-muted-foreground">
                                No hay productos agregados. Haz clic en
                                &quot;Agregar producto&quot;.
                            </div>
                        ) : (
                            <div className="space-y-3">
                                {data.items.map((item, index) => {
                                    const selectedProduct = products.find(
                                        (p) => p.id === Number(item.product_id),
                                    );
                                    const variantOptions = selectedProduct
                                        ? selectedProduct.variants.map((v) => ({
                                              id: String(v.id),
                                              label: v.name,
                                          }))
                                        : [];
                                    const itemErrors = errors as Record<
                                        string,
                                        string | undefined
                                    >;

                                    return (
                                        <FormLineItem
                                            key={index}
                                            index={index}
                                            onRemove={() => removeItem(index)}
                                            className="md:grid-cols-12"
                                        >
                                            <FormField
                                                id={`items.${index}.product_id`}
                                                label="Producto"
                                                required
                                                error={
                                                    itemErrors[
                                                        `items.${index}.product_id`
                                                    ]
                                                }
                                                className="md:col-span-4"
                                            >
                                                <Combobox
                                                    options={productOptions}
                                                    value={item.product_id}
                                                    onChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'product_id',
                                                            String(value),
                                                        )
                                                    }
                                                    placeholder="Seleccionar..."
                                                />
                                            </FormField>

                                            <FormField
                                                id={`items.${index}.product_variant_id`}
                                                label="Presentación"
                                                error={
                                                    itemErrors[
                                                        `items.${index}.product_variant_id`
                                                    ]
                                                }
                                                className="md:col-span-3"
                                            >
                                                <Combobox
                                                    options={variantOptions}
                                                    value={
                                                        item.product_variant_id
                                                    }
                                                    onChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'product_variant_id',
                                                            String(value),
                                                        )
                                                    }
                                                    placeholder="Seleccionar..."
                                                    disabled={!selectedProduct}
                                                />
                                            </FormField>

                                            <FormField
                                                id={`items.${index}.quantity`}
                                                label="Cantidad"
                                                required
                                                error={
                                                    itemErrors[
                                                        `items.${index}.quantity`
                                                    ]
                                                }
                                                className="md:col-span-2"
                                            >
                                                <IntegerInput
                                                    maxDigits={UNITS_MAX_DIGITS}
                                                    value={item.quantity}
                                                    onValueChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'quantity',
                                                            value,
                                                        )
                                                    }
                                                />
                                            </FormField>

                                            <FormField
                                                id={`items.${index}.color`}
                                                label="Color"
                                                error={
                                                    itemErrors[
                                                        `items.${index}.color`
                                                    ]
                                                }
                                                className="md:col-span-3"
                                            >
                                                <Input
                                                    value={item.color}
                                                    maxLength={100}
                                                    placeholder="Ej.: RAL 3020"
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'color',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </FormField>
                                        </FormLineItem>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <FormActions
                        submitLabel="Guardar Pedido"
                        processing={processing}
                        cancelHref={salesOrdersIndex().url}
                    />
                </form>
            </FormPage>
        </>
    );
}

SalesOrdersCreate.layout = {
    breadcrumbs: [
        { title: 'Pedidos', href: salesOrdersIndex() },
        { title: 'Nuevo pedido', href: salesOrdersCreate() },
    ],
};
