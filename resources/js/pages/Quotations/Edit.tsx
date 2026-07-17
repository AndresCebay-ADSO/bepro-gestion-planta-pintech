import { Head } from '@inertiajs/react';

import { update as quotationUpdate } from '@/actions/App/Http/Controllers/QuotationController';
import QuotationForm from '@/components/quotations/QuotationForm';
import type {ClientOption, ProductOption, QuotationFormData} from '@/components/quotations/QuotationForm';
import type {ComboboxOptionType} from '@/components/ui/combobox';
import { index as quotationsIndex } from '@/routes/quotations';

type QuotationDetail = {
    id: number;
    quotation_number: number | null;
    client_id: number;
    client: {
        business_name: string | null;
        nit: string | null;
        contact_name: string | null;
        phone: string | null;
    };
    technology: string | null;
    line: string | null;
    thickness_mils: string | null;
    application_method: string | null;
    quotation_date: string | null;
    validity_days: number | null;
    payment_method: string | null;
    delivery_time: string | null;
    area: string | null;
    notes: string | null;
    iva_percentage: number;
    items: Array<{
        product_id: number;
        product_variant_id: number;
        type: string | null;
        description: string | null;
        color: string | null;
        quantity: number;
        list_unit_price: number;
        price_adjustment_pct: number;
        unit_price: number;
    }>;
};

type Props = {
    quotation: QuotationDetail;
    clients: ClientOption[];
    products: ProductOption[];
    validityDaysOptions: ComboboxOptionType[];
    paymentMethodOptions: ComboboxOptionType[];
    itemTypeOptions: ComboboxOptionType[];
};

function mapQuotationToForm(quotation: QuotationDetail): QuotationFormData {
    return {
        client_id: String(quotation.client_id),
        client_business_name: quotation.client.business_name ?? '',
        client_nit: quotation.client.nit ?? '',
        client_contact_name: quotation.client.contact_name ?? '',
        client_phone: quotation.client.phone ?? '',
        technology: quotation.technology ?? '',
        line: quotation.line ?? '',
        thickness_mils: quotation.thickness_mils ?? '',
        application_method: quotation.application_method ?? '',
        quotation_date: quotation.quotation_date ?? '',
        validity_days:
            quotation.validity_days != null
                ? String(quotation.validity_days)
                : '',
        payment_method: quotation.payment_method ?? '',
        delivery_time: quotation.delivery_time ?? '',
        area: quotation.area ?? '',
        notes: quotation.notes ?? '',
        iva_percentage: String(quotation.iva_percentage),
        items: quotation.items.map((item) => ({
            product_id: String(item.product_id),
            product_variant_id: String(item.product_variant_id),
            type: item.type ?? '',
            description: item.description ?? '',
            color: item.color ?? '',
            quantity: String(item.quantity),
            list_unit_price: String(item.list_unit_price),
            price_adjustment_pct: String(item.price_adjustment_pct),
            unit_price: String(item.unit_price),
        })),
    };
}

export default function QuotationsEdit({
    quotation,
    clients,
    products,
    validityDaysOptions,
    paymentMethodOptions,
    itemTypeOptions,
}: Props) {
    return (
        <>
            <Head title={`Editar Cotización ${quotation.quotation_number ?? quotation.id}`} />
            <QuotationForm
                clients={clients}
                products={products}
                validityDaysOptions={validityDaysOptions}
                paymentMethodOptions={paymentMethodOptions}
                itemTypeOptions={itemTypeOptions}
                initialData={mapQuotationToForm(quotation)}
                submitUrl={quotationUpdate(quotation.id).url}
                method="put"
                title="Editar Cotización"
                backUrl={quotationsIndex().url}
                submitLabel="Actualizar Cotización"
            />
        </>
    );
}
