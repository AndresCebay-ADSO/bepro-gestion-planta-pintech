import { Link, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useEffect } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { FormattedNumber } from '@/components/formatted-number';
import InputError from '@/components/input-error';
import { IntegerInput } from '@/components/integer-input';
import { PageHeader } from '@/components/page-header';
import { PlantDateInput } from '@/components/plant-date-input';
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
import { useQuotationPricingPreview } from '@/hooks/use-quotation-pricing-preview';
import { formatNumber } from '@/lib/formatters';
import { NUMBER_TYPES, UNITS_MAX_DIGITS } from '@/lib/number-types';
import { toDecimalPayload } from '@/lib/numeric-input';
import {
    applyPricingLines,
    newRowKey,
    pricingFieldsFor,
} from '@/lib/quotation-pricing-rows';
import type { QuotationPricingRow } from '@/lib/quotation-pricing-rows';

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
    name: string;
    description: string | null;
    variants: VariantOption[];
};

export type ItemRow = QuotationPricingRow & {
    type: string;
    description: string;
    color: string;
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
    validityDaysOptions: { value: string; label: string }[];
    paymentMethodOptions: { value: string; label: string }[];
    itemTypeOptions: { value: string; label: string }[];
    initialData: QuotationFormData;
    submitUrl: string;
    /** Ruta de la vista previa de precios: al crear o al editar esta cotización. */
    previewUrl: string;
    method?: 'post' | 'put';
    title: string;
    backUrl: string;
    submitLabel: string;
};

export function buildEmptyItem(): ItemRow {
    return {
        row_key: newRowKey(),
        pricing_source: 'adjustment',
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
    previewUrl,
    method = 'post',
    title,
    backUrl,
    submitLabel,
}: Props) {
    const {
        data,
        setData,
        post,
        put,
        processing,
        errors,
        clearErrors,
        transform,
    } = useForm(initialData);
    // Los errores de las filas llegan como «items.0.quantity», que el tipo de useForm no enumera.
    const fieldErrors = errors as Record<string, string | undefined>;

    const clientOptions = clients.map((client) => ({
        id: String(client.id),
        label: `${client.business_name}${client.contact_name ? ` — ${client.contact_name}` : ''}`,
    }));

    const productOptions = products.map((product) => ({
        id: String(product.id),
        label: product.name,
    }));

    // El dinero lo calcula el servidor (PR 4b): el formulario muestra la vista previa y llena, de cada fila, el campo
    // de precio que el usuario no escribió (`pricing_source`).
    const {
        preview,
        lineFor,
        previewErrors,
        previewLoading,
        previewStale,
        previewIncompleteRows,
    } = useQuotationPricingPreview(data.items, data.iva_percentage, previewUrl);

    // Al llegar una vista previa y también al cambiar las filas: si una edición vuelve a la misma firma («15» → «15,»),
    // no se pide otra vista previa y el campo que se vació se llena con la línea que ya está. Cada línea trae la firma
    // de la fila para la que se calculó, y sin cambios no se escribe nada, así que no hay bucle.
    useEffect(() => {
        if (preview === null) {
            return;
        }

        const filled = applyPricingLines(data.items, preview.lines);

        if (filled !== null) {
            setData('items', filled);
        }
    }, [preview, data.items, setData]);

    // El error de la vista previa es el del cálculo actual; el del envío, el del último guardado.
    const itemError = (item: ItemRow, index: number, field: keyof ItemRow) =>
        previewErrors.byRow[item.row_key]?.[field] ??
        fieldErrors[`items.${index}.${field}`];

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault();

        transform((formData) => ({
            ...formData,
            iva_percentage: toDecimalPayload(formData.iva_percentage),
            // Solo lo que valida el servidor, y del precio solo el campo que escribió el usuario: el otro (y el precio de
            // lista) los calcula el servidor con la misma regla.
            items: formData.items.map((item) => ({
                product_id: item.product_id,
                product_variant_id: item.product_variant_id,
                type: item.type,
                description: item.description,
                color: item.color,
                quantity: item.quantity,
                ...pricingFieldsFor(item),
            })),
        }));

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
        // Los errores del último envío van por índice: al borrar una fila dejarían de corresponder.
        clearErrors(
            ...(Object.keys(fieldErrors).filter((field) =>
                field.startsWith('items.'),
            ) as Array<keyof typeof errors>),
        );
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
                next.pricing_source = 'adjustment';
            }

            if (field === 'product_variant_id') {
                const product = products.find(
                    (entry) => entry.id === Number(next.product_id),
                );
                const variant = product?.variants.find(
                    (entry) => entry.id === Number(value),
                );

                // El precio lo calcula la vista previa del servidor con el ajuste (0 %).
                next.list_unit_price =
                    variant?.sales_price != null
                        ? String(variant.sales_price)
                        : '';
                next.unit_price = '';
                next.price_adjustment_pct = '0';
                next.pricing_source = 'adjustment';
                next.description = product?.description ?? product?.name ?? '';
            }

            // El campo que escribe el usuario pasa a mandar; el otro se vacía y la vista previa lo calcula: el precio
            // desde el ajuste o el ajuste desde el precio. Así nunca se pisa lo que el usuario escribió.
            if (field === 'price_adjustment_pct') {
                next.pricing_source = 'adjustment';
                next.unit_price = '';
            }

            if (field === 'unit_price') {
                next.pricing_source = 'price';
                next.price_adjustment_pct = '';
            }

            return next;
        });

        setData('items', updated);

        const cleared: string[] = [`items.${index}.${field}`];

        if (field === 'price_adjustment_pct' || field === 'unit_price') {
            cleared.push(
                `items.${index}.price_adjustment_pct`,
                `items.${index}.unit_price`,
            );
        }

        clearErrors(...(cleared as Array<keyof typeof errors>));
    };

    return (
        <div className="mx-auto max-w-6xl space-y-6 p-6">
            <PageHeader title={title} />

            <form noValidate onSubmit={handleSubmit} className="space-y-6">
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
                            <Label htmlFor="quotation_date">
                                Fecha cotización
                            </Label>
                            <PlantDateInput
                                id="quotation_date"
                                notFuture
                                saved={
                                    method === 'put'
                                        ? initialData.quotation_date
                                        : null
                                }
                                value={data.quotation_date}
                                onChange={(value) =>
                                    setData('quotation_date', value)
                                }
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="validity_days">
                                Validez (días)
                            </Label>
                            <Select
                                value={data.validity_days}
                                onValueChange={(v) =>
                                    setData('validity_days', v)
                                }
                            >
                                <SelectTrigger id="validity_days">
                                    <SelectValue placeholder="Seleccionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {validityDaysOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="payment_method">
                                Forma de pago
                            </Label>
                            <Select
                                value={data.payment_method}
                                onValueChange={(v) =>
                                    setData('payment_method', v)
                                }
                            >
                                <SelectTrigger id="payment_method">
                                    <SelectValue placeholder="Seleccionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {paymentMethodOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
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
                        <Label htmlFor="notes">Observaciones</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <p className="text-xs text-muted-foreground">
                            Opcional. Solo para condiciones particulares de esta
                            cotización (transporte, pedido mínimo, anticipo…).
                            Sale en el PDF; si no aplica, déjelo vacío.
                        </p>
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

                    <InputError
                        message={errors.items ?? previewErrors.general.items}
                    />

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
                                const { line, isCurrent } = lineFor(
                                    item.row_key,
                                );
                                // El campo que manda, si está vacío, muestra lo que se va a usar: sin precio se cotiza
                                // al 0 % y sin ajuste también. No se escribe en el campo mientras el usuario lo edita.
                                const appliedPrice =
                                    item.pricing_source === 'price' &&
                                    item.unit_price === '' &&
                                    isCurrent
                                        ? String(Number(line?.unit_price))
                                        : undefined;

                                return (
                                    <div
                                        key={item.row_key}
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
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'product_id',
                                                    )}
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
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'product_variant_id',
                                                    )}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label
                                                    htmlFor={`items.${index}.type`}
                                                    className="text-xs"
                                                >
                                                    Tipo
                                                </Label>
                                                <Select
                                                    value={item.type}
                                                    onValueChange={(v) =>
                                                        updateItem(
                                                            index,
                                                            'type',
                                                            v,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id={`items.${index}.type`}
                                                    >
                                                        <SelectValue placeholder="Seleccionar..." />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {itemTypeOptions.map(
                                                            (option) => (
                                                                <SelectItem
                                                                    key={
                                                                        option.value
                                                                    }
                                                                    value={
                                                                        option.value
                                                                    }
                                                                >
                                                                    {
                                                                        option.label
                                                                    }
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'type',
                                                    )}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Cantidad *
                                                </Label>
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
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'quantity',
                                                    )}
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
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'description',
                                                    )}
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
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'color',
                                                    )}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Precio lista
                                                </Label>
                                                <Input
                                                    // Precio interno: 2 decimales, y vacío sin presentación.
                                                    value={formatNumber(
                                                        item.list_unit_price,
                                                        {
                                                            currency: '$',
                                                            maxDecimals: 2,
                                                            emptyValue: '',
                                                        },
                                                    )}
                                                    readOnly
                                                    className="bg-muted/50"
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Ajuste %
                                                </Label>
                                                <DecimalInput
                                                    {...NUMBER_TYPES.percentage}
                                                    allowNegative
                                                    value={
                                                        item.price_adjustment_pct
                                                    }
                                                    onValueChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'price_adjustment_pct',
                                                            value,
                                                        )
                                                    }
                                                    placeholder={
                                                        item.pricing_source ===
                                                        'adjustment'
                                                            ? '0'
                                                            : '-10 o 5'
                                                    }
                                                />
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'price_adjustment_pct',
                                                    )}
                                                />
                                            </div>
                                            <div className="md:col-span-2">
                                                <Label className="text-xs">
                                                    Precio unitario
                                                </Label>
                                                <IntegerInput
                                                    maxDigits={
                                                        NUMBER_TYPES.money
                                                            .maxIntegerDigits
                                                    }
                                                    value={item.unit_price}
                                                    onValueChange={(value) =>
                                                        updateItem(
                                                            index,
                                                            'unit_price',
                                                            value,
                                                        )
                                                    }
                                                    placeholder={appliedPrice}
                                                />
                                                <InputError
                                                    className="text-xs"
                                                    message={itemError(
                                                        item,
                                                        index,
                                                        'unit_price',
                                                    )}
                                                />
                                            </div>
                                        </div>

                                        <div className="text-right text-sm text-muted-foreground">
                                            Subtotal línea:{' '}
                                            {/* Atenuado si no corresponde a la fila tal como está: se está
                                                recalculando o la vista previa fue rechazada. */}
                                            <FormattedNumber
                                                value={line?.subtotal ?? null}
                                                currency
                                                maxDecimals={0}
                                                className={
                                                    isCurrent
                                                        ? undefined
                                                        : 'opacity-50'
                                                }
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
                        {/* Informativo, no un error: es lo normal mientras se llena un producto nuevo. Al guardar, el
                            servidor marca los campos que le faltan a la fila. */}
                        {previewIncompleteRows > 0 && (
                            <p className="text-xs text-muted-foreground">
                                {previewIncompleteRows === 1
                                    ? '1 fila incompleta no suma en los totales.'
                                    : `${previewIncompleteRows} filas incompletas no suman en los totales.`}
                            </p>
                        )}
                        {(previewLoading || previewStale !== null) && (
                            <p
                                className={
                                    previewStale === null
                                        ? 'text-xs text-muted-foreground'
                                        : 'text-xs text-destructive'
                                }
                            >
                                {previewStale === null
                                    ? 'Calculando precios…'
                                    : previewStale === 'invalid'
                                      ? 'Revisa los datos marcados: los totales no están al día.'
                                      : 'No se pudieron calcular los precios. Se recalculan al guardar.'}
                            </p>
                        )}
                        <div className="flex items-center justify-between">
                            <span>Subtotal</span>
                            <FormattedNumber
                                value={preview?.totals.subtotal ?? 0}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        {/* items-start: si aparece un mensaje bajo el IVA, el texto de la fila no baja con él. */}
                        <div className="flex items-start justify-between leading-8">
                            <div className="flex items-start gap-2">
                                <span>IVA</span>
                                <div>
                                    <div className="flex items-start gap-1">
                                        <DecimalInput
                                            maxDecimals={2}
                                            maxIntegerDigits={3}
                                            value={data.iva_percentage}
                                            onValueChange={(value) => {
                                                setData(
                                                    'iva_percentage',
                                                    value,
                                                );
                                                clearErrors('iva_percentage');
                                            }}
                                            className="h-8 w-20 text-right"
                                        />
                                        <span>%</span>
                                    </div>
                                    <InputError
                                        className="text-xs leading-4"
                                        message={
                                            errors.iva_percentage ??
                                            previewErrors.general.iva_percentage
                                        }
                                    />
                                </div>
                            </div>
                            <FormattedNumber
                                value={preview?.totals.iva_amount ?? 0}
                                currency
                                maxDecimals={0}
                            />
                        </div>
                        <div className="flex items-center justify-between border-t border-border pt-2 text-base font-semibold">
                            <span>Total</span>
                            <FormattedNumber
                                value={preview?.totals.total ?? 0}
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
