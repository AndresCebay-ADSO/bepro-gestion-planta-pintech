import { useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useEffect } from 'react';

import { DecimalInput } from '@/components/decimal-input';
import { FormActions } from '@/components/form-actions';
import { FormField } from '@/components/form-field';
import { FormLineItem } from '@/components/form-line-item';
import { FormPage } from '@/components/form-page';
import { FormattedNumber } from '@/components/formatted-number';
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
import { useQuotationPricingPreview } from '@/hooks/use-quotation-pricing-preview';
import { clearListErrors } from '@/lib/form-errors';
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
        clearListErrors(fieldErrors, clearErrors, 'items');
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
        <FormPage width="wide">
            <PageHeader title={title} />

            <form noValidate onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                    <h2 className="text-lg font-semibold">Cliente</h2>
                    <div className="grid gap-4 md:grid-cols-2">
                        <FormField
                            id="client_id"
                            label="Cliente"
                            required
                            error={errors.client_id}
                            className="md:col-span-2"
                        >
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
                        </FormField>

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
                            />
                        </FormField>
                    </div>
                </div>

                <div className="space-y-4 rounded-lg border border-border bg-card p-6">
                    <h2 className="text-lg font-semibold">
                        Condiciones comerciales
                    </h2>
                    <div className="grid gap-4 md:grid-cols-3">
                        <FormField
                            id="technology"
                            label="Tecnología"
                            error={errors.technology}
                        >
                            <Input
                                value={data.technology}
                                onChange={(e) =>
                                    setData('technology', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField id="line" label="Línea" error={errors.line}>
                            <Input
                                value={data.line}
                                onChange={(e) =>
                                    setData('line', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField id="area" label="Área" error={errors.area}>
                            <Input
                                value={data.area}
                                onChange={(e) =>
                                    setData('area', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="thickness_mils"
                            label="Espesor (mils)"
                            error={errors.thickness_mils}
                        >
                            <Input
                                value={data.thickness_mils}
                                onChange={(e) =>
                                    setData('thickness_mils', e.target.value)
                                }
                            />
                        </FormField>
                        <FormField
                            id="application_method"
                            label="Método de aplicación"
                            error={errors.application_method}
                        >
                            <Input
                                value={data.application_method}
                                onChange={(e) =>
                                    setData(
                                        'application_method',
                                        e.target.value,
                                    )
                                }
                            />
                        </FormField>
                        <FormField
                            id="quotation_date"
                            label="Fecha cotización"
                            error={errors.quotation_date}
                        >
                            <PlantDateInput
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
                        </FormField>
                        <FormField
                            id="validity_days"
                            label="Validez (días)"
                            error={errors.validity_days}
                        >
                            {(control) => (
                                <Select
                                    value={data.validity_days}
                                    onValueChange={(v) =>
                                        setData('validity_days', v)
                                    }
                                >
                                    <SelectTrigger {...control}>
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
                            )}
                        </FormField>
                        <FormField
                            id="payment_method"
                            label="Forma de pago"
                            error={errors.payment_method}
                        >
                            {(control) => (
                                <Select
                                    value={data.payment_method}
                                    onValueChange={(v) =>
                                        setData('payment_method', v)
                                    }
                                >
                                    <SelectTrigger {...control}>
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
                            )}
                        </FormField>
                        <FormField
                            id="delivery_time"
                            label="Tiempo de entrega"
                            error={errors.delivery_time}
                        >
                            <Input
                                value={data.delivery_time}
                                onChange={(e) =>
                                    setData('delivery_time', e.target.value)
                                }
                            />
                        </FormField>
                    </div>
                    <FormField
                        id="notes"
                        label="Observaciones"
                        error={errors.notes}
                        help="Opcional. Solo para condiciones particulares de esta cotización (transporte, pedido mínimo, anticipo…). Sale en el PDF; si no aplica, déjelo vacío."
                    >
                        <Textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
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
                                    <FormLineItem
                                        key={item.row_key}
                                        index={index}
                                        onRemove={() => removeItem(index)}
                                        className="md:grid-cols-12"
                                        footer={
                                            <div className="text-right text-sm text-muted-foreground">
                                                Subtotal línea:{' '}
                                                {/* Atenuado si no corresponde a la fila tal como está: se está
                                                    recalculando o la vista previa fue rechazada. */}
                                                <FormattedNumber
                                                    value={
                                                        line?.subtotal ?? null
                                                    }
                                                    currency
                                                    maxDecimals={0}
                                                    className={
                                                        isCurrent
                                                            ? undefined
                                                            : 'opacity-50'
                                                    }
                                                />
                                            </div>
                                        }
                                    >
                                        <FormField
                                            id={`items.${index}.product_id`}
                                            label="Producto"
                                            required
                                            error={itemError(
                                                item,
                                                index,
                                                'product_id',
                                            )}
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
                                            required
                                            error={itemError(
                                                item,
                                                index,
                                                'product_variant_id',
                                            )}
                                            className="md:col-span-3"
                                        >
                                            <Combobox
                                                options={variantOptions}
                                                value={item.product_variant_id}
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
                                            id={`items.${index}.type`}
                                            label="Tipo"
                                            error={itemError(
                                                item,
                                                index,
                                                'type',
                                            )}
                                            className="md:col-span-2"
                                        >
                                            {(control) => (
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
                                                    <SelectTrigger {...control}>
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
                                            )}
                                        </FormField>
                                        <FormField
                                            id={`items.${index}.quantity`}
                                            label="Cantidad"
                                            required
                                            error={itemError(
                                                item,
                                                index,
                                                'quantity',
                                            )}
                                            className="md:col-span-3"
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
                                            id={`items.${index}.description`}
                                            label="Descripción comercial"
                                            error={itemError(
                                                item,
                                                index,
                                                'description',
                                            )}
                                            className="md:col-span-4"
                                        >
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
                                        </FormField>
                                        <FormField
                                            id={`items.${index}.color`}
                                            label="Color"
                                            error={itemError(
                                                item,
                                                index,
                                                'color',
                                            )}
                                            className="md:col-span-2"
                                        >
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
                                        </FormField>
                                        <FormField
                                            id={`items.${index}.list_unit_price`}
                                            label="Precio lista"
                                            className="md:col-span-2"
                                        >
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
                                        </FormField>
                                        <FormField
                                            id={`items.${index}.price_adjustment_pct`}
                                            label="Ajuste %"
                                            error={itemError(
                                                item,
                                                index,
                                                'price_adjustment_pct',
                                            )}
                                            className="md:col-span-2"
                                        >
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
                                        </FormField>
                                        <FormField
                                            id={`items.${index}.unit_price`}
                                            label="Precio unitario"
                                            error={itemError(
                                                item,
                                                index,
                                                'unit_price',
                                            )}
                                            className="md:col-span-2"
                                        >
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
                                        </FormField>
                                    </FormLineItem>
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

                <FormActions
                    submitLabel={submitLabel}
                    processing={processing}
                    cancelHref={backUrl}
                />
            </form>
        </FormPage>
    );
}
