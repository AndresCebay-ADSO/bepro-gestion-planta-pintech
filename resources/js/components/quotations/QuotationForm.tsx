import { Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus, Trash2 } from 'lucide-react';
import { useMemo } from 'react';

import { FormattedNumber } from '@/components/formatted-number';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import type { ComboboxOptionType } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ClientOption = {
    id: number;
    business_name: string;
    nit: string | null;
    contact_name: string | null;
    phone: string | null;
};

export type VariantOption = {
    id: number;
    name: string;
    presentation_label: string | null;
    presentation_value: number | null;
    sales_price: number | null;
};

export type ProductOption = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    variants: VariantOption[];
};

export type ItemRow = {
    product_id: string;
    product_variant_id: string;
    type: string;
    description: string;
    color: string;
    quantity: string;
    list_unit_price: string;
    price_adjustment_pct: string;
    unit_price: string;
};

export type QuotationFormData = {
    client_id: string;
    client_business_name: string;
    client_nit: string;
    client_contact_name: string;
    client_phone: string;
    technology: string;
    line: string;
    thickness_mils: string;
    application_method: string;
    quotation_date: string;
    validity_days: string;
    payment_method: string;
    delivery_time: string;
    area: string;
    notes: string;
    iva_percentage: string;
    items: ItemRow[];
};

type Props = {
    clients: ClientOption[];
    products: ProductOption[];
    validityDaysOptions: ComboboxOptionType[];
    paymentMethodOptions: ComboboxOptionType[];
    itemTypeOptions: ComboboxOptionType[];
    initialData: QuotationFormData;
    submitUrl: string;
    method?: 'post' | 'put';
    title: string;
    backUrl: string;
    submitLabel: string;
};

/**
 * Business Rule — Margin-based adjustment:
 * The adjustment modifies the profit margin, not a simple discount/surcharge.
 * Positive values increase the margin (price goes up), negative values decrease
 * it (price goes down).
 *
 * Formula:  unit_price = list_price / (1 - adjustmentPct / 100)
 *
 * Examples:
 *   adjustment = +15%  →  divisor = 0.85   →  price = list / 0.85
 *   adjustment = -15%  →  divisor = 1.15   →  price = list / 1.15
 *   adjustment =   0%  →  divisor = 1.00   →  price = list
 *
 * This is intentionally asymmetric: a +15% and -15% adjustment do NOT cancel
 * each other out, because they operate on the margin space.
 */
function applyAdjustment(
    listPrice: number,
    adjustmentPct: number,
): number | null {
    const divisor = 1 - adjustmentPct / 100;

    if (divisor <= 0) {
        return null;
    }

    return parseFloat((listPrice / divisor).toFixed(4));
}

function calculateLineSubtotal(quantity: string, unitPrice: string): number {
    const qty = Number(quantity) || 0;
    const price = Number(unitPrice) || 0;

    return qty * price;
}

export function buildEmptyItem(): ItemRow {
    return {
        product_id: '',
        product_variant_id: '',
        type: '',
        description: '',
        color: '',
        quantity: '',
        list_unit_price: '',
        price_adjustment_pct: '0',
        unit_price: '',
    };
}

export default function QuotationForm({
    clients,
    products,
    validityDaysOptions,
    paymentMethodOptions,
    itemTypeOptions,
    initialData,
    submitUrl,
    method = 'post',
    title,
    backUrl,
    submitLabel,
}: Props) {
    const { data, setData, post, put, processing, errors } =
        useForm(initialData);

    const clientOptions = clients.map((client) => ({
        id: String(client.id),
        label: `${client.business_name}${client.contact_name ? ` — ${client.contact_name}` : ''}`,
    }));

    const productOptions = products.map((product) => ({
        id: String(product.id),
        label: `${product.code} — ${product.name}`,
    }));

    const totals = useMemo(() => {
        const subtotal = data.items.reduce(
            (sum, item) =>
                sum + calculateLineSubtotal(item.quantity, item.unit_price),
            0,
        );
        const ivaPercentage = Number(data.iva_percentage) || 0;
        const ivaAmount = subtotal * (ivaPercentage / 100);

        return {
            subtotal,
            ivaAmount,
            total: subtotal + ivaAmount,
        };
    }, [data.items, data.iva_percentage]);

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        if (method === 'put') {
            put(submitUrl);
        } else {
            post(submitUrl);
        }
    };

    const addItem = () => {
        setData('items', [...data.items, buildEmptyItem()]);
    };

    const removeItem = (index: number) => {
        setData(
            'items',
            data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const updateItem = <K extends keyof ItemRow>(
        index: number,
        field: K,
        value: ItemRow[K],
    ) => {
        const updated = data.items.map((item, itemIndex) => {
            if (itemIndex !== index) {
                return item;
            }

            const next = { ...item, [field]: value };

            if (field === 'product_id') {
                next.product_variant_id = '';
                next.description = '';
                next.list_unit_price = '';
                next.unit_price = '';
                next.price_adjustment_pct = '0';
            }

            if (field === 'product_variant_id') {
                const product = products.find(
                    (entry) => entry.id === Number(next.product_id),
                );
                const variant = product?.variants.find(
                    (entry) => entry.id === Number(value),
                );

                if (variant?.sales_price != null) {
                    next.list_unit_price = String(variant.sales_price);
                    next.unit_price = String(variant.sales_price);
                    next.price_adjustment_pct = '0';
                }

                next.description = product?.description ?? product?.name ?? '';
            }

            if (field === 'price_adjustment_pct') {
                const listPrice = Number(next.list_unit_price) || 0;
                const adjustment = Number(value) || 0;
                const adjustedPrice = applyAdjustment(listPrice, adjustment);

                if (adjustedPrice !== null) {
                    next.unit_price = String(adjustedPrice.toFixed(4));
                }
            }

            if (field === 'unit_price') {
                const listPrice = Number(next.list_unit_price) || 0;
                const unitPrice = Number(value) || 0;

                // Inverse of the margin formula:
                //   adjustment = (1 - listPrice / unitPrice) * 100
                if (listPrice > 0 && unitPrice > 0) {
                    next.price_adjustment_pct = String(
                        ((1 - listPrice / unitPrice) * 100).toFixed(2),
                    );
                } else {
                    next.price_adjustment_pct = '0';
                }
            }

            return next;
        });

        setData('items', updated);
    };

    return (
        <div className="mx-auto max-w-6xl space-y-6 p-6">
            <div className="flex items-center gap-4">
                <Button variant="ghost" size="sm" asChild>
                    <Link href={backUrl}>
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Volver
                    </Link>
                </Button>
                <h1 className="text-2xl font-semibold">{title}</h1>
            </div>

            <form onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                    <h2 className="text-lg font-semibold">Cliente</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="space-y-2 md:col-span-2">
                            <Label htmlFor="client_id">Cliente *</Label>
                            <Combobox
                                options={clientOptions}
                                value={data.client_id}
                                onChange={(value) => {
                                    const client = clients.find(
                                        (entry) => String(entry.id) === value,
                                    );

                                    setData('client_id', String(value));

                                    if (client) {
                                        setData(
                                            'client_business_name',
                                            client.business_name,
                                        );
                                        setData('client_nit', client.nit ?? '');
                                        setData(
                                            'client_contact_name',
                                            client.contact_name ?? '',
                                        );
                                        setData(
                                            'client_phone',
                                            client.phone ?? '',
                                        );
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
                            <Label>Razón social</Label>
                            <Input
                                value={data.client_business_name}
                                readOnly
                                className="bg-muted/50"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>NIT</Label>
                            <Input
                                value={data.client_nit}
                                readOnly
                                className="bg-muted/50"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Contacto</Label>
                            <Input
                                value={data.client_contact_name}
                                onChange={(e) =>
                                    setData(
                                        'client_contact_name',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Teléfono</Label>
                            <Input
                                value={data.client_phone}
                                onChange={(e) =>
                                    setData('client_phone', e.target.value)
                                }
                            />
                        </div>
                    </div>
                </div>

                <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                    <h2 className="text-lg font-semibold">
                        Condiciones comerciales
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        <div className="space-y-2">
                            <Label>Tecnología</Label>
                            <Input
                                value={data.technology}
                                onChange={(e) =>
                                    setData('technology', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Línea</Label>
                            <Input
                                value={data.line}
                                onChange={(e) =>
                                    setData('line', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Área</Label>
                            <Input
                                value={data.area}
                                onChange={(e) =>
                                    setData('area', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Espesor (mils)</Label>
                            <Input
                                value={data.thickness_mils}
                                onChange={(e) =>
                                    setData('thickness_mils', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Método de aplicación</Label>
                            <Input
                                value={data.application_method}
                                onChange={(e) =>
                                    setData(
                                        'application_method',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Fecha cotización</Label>
                            <Input
                                type="date"
                                value={data.quotation_date}
                                onChange={(e) =>
                                    setData('quotation_date', e.target.value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Validez (días)</Label>
                            <Combobox
                                options={validityDaysOptions}
                                value={data.validity_days}
                                onChange={(value) =>
                                    setData('validity_days', String(value))
                                }
                                placeholder="Seleccionar..."
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Forma de pago</Label>
                            <Combobox
                                options={paymentMethodOptions}
                                value={data.payment_method}
                                onChange={(value) =>
                                    setData('payment_method', String(value))
                                }
                                placeholder="Seleccionar..."
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Tiempo de entrega</Label>
                            <Input
                                value={data.delivery_time}
                                onChange={(e) =>
                                    setData('delivery_time', e.target.value)
                                }
                            />
                        </div>
                    </div>
                    <div className="space-y-2">
                        <Label>Notas</Label>
                        <Textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
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
                            Agrega al menos un producto para cotizar.
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {data.items.map((item, index) => {
                                const selectedProduct = products.find(
                                    (product) =>
                                        product.id === Number(item.product_id),
                                );
                                const variantOptions = selectedProduct
                                    ? selectedProduct.variants.map(
                                          (variant) => ({
                                              id: String(variant.id),
                                              label: `${variant.name}${variant.presentation_label ? ` (${variant.presentation_label})` : ''}`,
                                          }),
                                      )
                                    : [];
                                const lineSubtotal = calculateLineSubtotal(
                                    item.quantity,
                                    item.unit_price,
                                );

                                return (
                                    <div
                                        key={index}
                                        className="space-y-3 rounded border border-border p-4"
                                    >
                                        <div className="grid gap-3 md:grid-cols-12">
                                            <div className="md:col-span-4">
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
                                            </div>
                                            <div className="md:col-span-3">
                                                <Label className="text-xs">
                                                    Presentación *
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
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Tipo
                                                </Label>
                                                <Combobox
                                                    options={itemTypeOptions}
                                                    value={item.type}
                                                    onChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'type',
                                                            String(value),
                                                        )
                                                    }
                                                    placeholder="Seleccionar..."
                                                />
                                            </div>
                                            <div className="md:col-span-2">
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

                                        <div className="grid gap-3 md:grid-cols-12">
                                            <div className="md:col-span-4">
                                                <Label className="text-xs">
                                                    Descripción comercial
                                                </Label>
                                                <Input
                                                    value={item.description}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'description',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Color
                                                </Label>
                                                <Input
                                                    value={item.color}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'color',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Precio lista
                                                </Label>
                                                <Input
                                                    value={item.list_unit_price}
                                                    readOnly
                                                    className="bg-muted/50"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Ajuste %
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    value={
                                                        item.price_adjustment_pct
                                                    }
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'price_adjustment_pct',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="-10 o +5"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Precio unitario
                                                </Label>
                                                <Input
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    value={item.unit_price}
                                                    onChange={(e) =>
                                                        updateItem(
                                                            index,
                                                            'unit_price',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="text-right text-sm text-muted-foreground">
                                            Subtotal línea:{' '}
                                            <FormattedNumber
                                                value={lineSubtotal}
                                                currency
                                                maxDecimals={0}
                                            />
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="ml-auto max-w-sm space-y-4 text-sm">
                        <div className="flex items-center justify-between">
                            <span>Subtotal</span>
                            <FormattedNumber
                                value={totals.subtotal}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <span>IVA</span>
                                <div className="flex items-center gap-1">
                                    <Input
                                        type="number"
                                        min="0"
                                        max="100"
                                        step="0.1"
                                        value={data.iva_percentage}
                                        onChange={(e) =>
                                            setData(
                                                'iva_percentage',
                                                e.target.value,
                                            )
                                        }
                                        className="h-8 w-20 text-right"
                                    />
                                    <span>%</span>
                                </div>
                            </div>
                            <FormattedNumber
                                value={totals.ivaAmount}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        <div className="flex items-center justify-between border-t border-border pt-2 text-base font-semibold">
                            <span>Total</span>
                            <FormattedNumber
                                value={totals.total}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-3">
                    <Button variant="outline" asChild>
                        <Link href={backUrl}>Cancelar</Link>
                    </Button>
                    <Button type="submit" disabled={processing}>
                        {submitLabel}
                    </Button>
                </div>
            </form>
        </div>
    );
}
