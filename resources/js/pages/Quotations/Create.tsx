import { Head } from '@inertiajs/react';

import { store as quotationStore } from '@/actions/App/Http/Controllers/QuotationController';
import QuotationForm, {
    buildEmptyItem,
} from '@/components/quotations/QuotationForm';
import type {
    ClientOption,
    ProductOption,
} from '@/components/quotations/QuotationForm';
import type { ComboboxOptionType } from '@/components/ui/combobox';
import { getLocalDateString } from '@/lib/date-time-helpers';
import { index as quotationsIndex } from '@/routes/quotations';

type Props = {
    clients: ClientOption[];
    products: ProductOption[];
    validityDaysOptions: ComboboxOptionType[];
    paymentMethodOptions: ComboboxOptionType[];
    itemTypeOptions: ComboboxOptionType[];
};

export default function QuotationsCreate({
    clients,
    products,
    validityDaysOptions,
    paymentMethodOptions,
    itemTypeOptions,
}: Props) {
    const today = getLocalDateString();

    return (
        <>
            <Head title="Nueva Cotización" />
            <QuotationForm
                clients={clients}
                products={products}
                validityDaysOptions={validityDaysOptions}
                paymentMethodOptions={paymentMethodOptions}
                itemTypeOptions={itemTypeOptions}
                initialData={{
                    client_id: '',
                    client_business_name: '',
                    client_nit: '',
                    client_contact_name: '',
                    client_phone: '',
                    technology: '',
                    line: '',
                    thickness_mils: '',
                    application_method: '',
                    quotation_date: today,
                    validity_days: '',
                    payment_method: '',
                    delivery_time: '',
                    area: '',
                    notes: '',
                    iva_percentage: '19',
                    items: [buildEmptyItem()],
                }}
                submitUrl={quotationStore().url}
                title="Nueva Cotización"
                backUrl={quotationsIndex().url}
                submitLabel="Guardar Cotización"
            />
        </>
    );
}
