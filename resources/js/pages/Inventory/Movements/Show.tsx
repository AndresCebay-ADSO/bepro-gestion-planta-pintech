import { Head } from '@inertiajs/react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';

type Props = {
    movement: {
        id: number;
        type: string;
        quantity: string;
        movement_date: string;
        raw_material?: { code: string } | null;
    };
};

export default function InventoryMovementsShow({ movement }: Props) {
    return (
        <>
            <Head title={`Movimiento #${movement.id}`} />
            <div className="space-y-3 p-6">
                <PageHeader
                    title={`Movimiento #${movement.id}`}
                    description={
                        <>
                            {movement.type} -{' '}
                            <FormattedNumber
                                value={movement.quantity}
                                maxDecimals={2}
                            />{' '}
                            -{' '}
                            <FormattedDate
                                value={movement.movement_date}
                                format="date"
                            />
                        </>
                    }
                />
            </div>
        </>
    );
}
