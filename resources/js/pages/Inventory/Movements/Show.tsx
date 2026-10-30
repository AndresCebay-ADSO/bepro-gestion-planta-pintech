import { Head } from '@inertiajs/react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';
import { PageHeader } from '@/components/page-header';
import {
    index as inventoryMovementsIndex,
    show as inventoryMovementsShow,
} from '@/routes/inventory-movements';

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
            <div className="space-y-6 p-6">
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

// Las migas son la única forma de volver al listado (DESIGN.md §10).
InventoryMovementsShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'Movimientos de MP', href: inventoryMovementsIndex() },
        {
            title: `Movimiento #${props.movement.id}`,
            href: inventoryMovementsShow(props.movement.id),
        },
    ],
});
