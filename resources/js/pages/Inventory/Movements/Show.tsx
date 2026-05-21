import { Head } from '@inertiajs/react';

import { FormattedDate } from '@/components/formatted-date';
import { FormattedNumber } from '@/components/formatted-number';

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
            <Head title={`Movimiento ${movement.id}`} />
            <div className="space-y-3 p-6">
                <h1 className="text-2xl font-semibold">
                    Movimiento #{movement.id}
                </h1>
                <p className="text-sm text-muted-foreground">
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
                </p>
            </div>
        </>
    );
}
