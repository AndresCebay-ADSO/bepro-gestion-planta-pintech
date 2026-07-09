import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';

import { store as salesOrderStore } from '@/actions/App/Http/Controllers/SalesOrderController';
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
import { index as salesOrdersIndex } from '@/routes/sales-orders';

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
    code: string;
    name: string;
    variants: VariantOption[];
};

type ItemRow = {
    product_id: string;
    product_variant_id: string;
    quantity: string;
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

    const clientOptions = clients.map((c) => ({
        id: String(c.id),
        label: `${c.business_name}${c.contact_name ? ` — ${c.contact_name}` : ''}`,
    }));

    const productOptions = products.map((p) => ({
        id: String(p.id),
        label: `${p.code} — ${p.name}`,
    }));

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();
        post(salesOrderStore().url);
    };

    const addItem = () => {
        setData('items', [
            ...data.items,
            { product_id: '', product_variant_id: '', quantity: '' },
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
            <Head title="Nuevo Pedido" />

            <div className="mx-auto max-w-4xl space-y-6 p-6">
                <div className="flex items-center gap-4">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={salesOrdersIndex().url}>
                            <ArrowLeft className="mr-1 h-4 w-4" />
                            Volver
                        </Link>
                    </Button>
                    <h1 className="text-2xl font-semibold">Nuevo Pedido</h1>
                </div>

                <form onSubmit={handleSubmit} className="space-y-6">
                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <h2 className="text-lg font-semibold">
                            Información General
                        </h2>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="client_id">Cliente *</Label>
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
                                {errors.client_id && (
                                    <p className="text-sm text-destructive">
                                        {errors.client_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="required_date">
                                    Fecha requerida *
                                </Label>
                                <Input
                                    id="required_date"
                                    type="date"
                                    value={data.required_date}
                                    onChange={(e) =>
                                        setData('required_date', e.target.value)
                                    }
                                />
                                {errors.required_date && (
                                    <p className="text-sm text-destructive">
                                        {errors.required_date}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="priority">Prioridad *</Label>
                                <Select
                                    value={data.priority}
                                    onValueChange={(value) =>
                                        setData('priority', value)
                                    }
                                >
                                    <SelectTrigger id="priority">
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
                                {errors.priority && (
                                    <p className="text-sm text-destructive">
                                        {errors.priority}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="client_business_name">
                                    Razón social
                                </Label>
                                <Input
                                    id="client_business_name"
                                    value={data.client_business_name}
                                    readOnly
                                    className="bg-muted/50"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="client_nit">NIT</Label>
                                <Input
                                    id="client_nit"
                                    value={data.client_nit}
                                    readOnly
                                    className="bg-muted/50"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="client_contact_name">
                                    Contacto
                                </Label>
                                <Input
                                    id="client_contact_name"
                                    value={data.client_contact_name}
                                    onChange={(e) =>
                                        setData(
                                            'client_contact_name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Nombre del contacto para este pedido"
                                />
                                {errors.client_contact_name && (
                                    <p className="text-sm text-destructive">
                                        {errors.client_contact_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="client_phone">Teléfono</Label>
                                <Input
                                    id="client_phone"
                                    value={data.client_phone}
                                    onChange={(e) =>
                                        setData('client_phone', e.target.value)
                                    }
                                    placeholder="Teléfono de contacto para este pedido"
                                />
                                {errors.client_phone && (
                                    <p className="text-sm text-destructive">
                                        {errors.client_phone}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="shipping_address">
                                Dirección de entrega
                            </Label>
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

                        <div className="space-y-2">
                            <Label htmlFor="notes">Notas</Label>
                            <Textarea
                                id="notes"
                                value={data.notes}
                                onChange={(e) =>
                                    setData('notes', e.target.value)
                                }
                                placeholder="Observaciones adicionales..."
                            />
                            {errors.notes && (
                                <p className="text-sm text-destructive">
                                    {errors.notes}
                                </p>
                            )}
                        </div>
                    </div>

                    <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                        <div className="flex items-center justify-between">
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

                        {errors.items && (
                            <p className="text-sm text-destructive">
                                {errors.items}
                            </p>
                        )}

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

                                    return (
                                        <div
                                            key={index}
                                            className="grid grid-cols-1 gap-3 rounded border border-border p-3 md:grid-cols-12"
                                        >
                                            <div className="space-y-1 md:col-span-5">
                                                <Label className="text-xs">
                                                    Producto *
                                                </Label>
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
                                                {errors[
                                                    `items.${index}.product_id`
                                                ] && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors[
                                                                `items.${index}.product_id`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-1 md:col-span-4">
                                                <Label className="text-xs">
                                                    Presentación
                                                </Label>
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
                                                {errors[
                                                    `items.${index}.product_variant_id`
                                                ] && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors[
                                                                `items.${index}.product_variant_id`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="space-y-1 md:col-span-2">
                                                <Label className="text-xs">
                                                    Cantidad *
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.0001"
                                                    min="0.0001"
                                                    value={item.quantity}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'quantity',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                                {errors[
                                                    `items.${index}.quantity`
                                                ] && (
                                                    <p className="text-xs text-destructive">
                                                        {
                                                            errors[
                                                                `items.${index}.quantity`
                                                            ]
                                                        }
                                                    </p>
                                                )}
                                            </div>

                                            <div className="flex items-end justify-end md:col-span-1">
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() =>
                                                        removeItem(index)
                                                    }
                                                >
                                                    <Trash2 className="h-4 w-4 text-destructive" />
                                                </Button>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <Button variant="outline" asChild>
                            <Link href={salesOrdersIndex().url}>Cancelar</Link>
                        </Button>
                        <Button type="submit" disabled={processing}>
                            Guardar Pedido
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}
