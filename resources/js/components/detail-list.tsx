import type { ReactNode } from 'react';

import { EmptyCell } from '@/components/empty-cell';
import { isEmptyCell } from '@/lib/data-listing';
import { cn } from '@/lib/utils';

// Una columna en el celular; `grid-cols-1` (y no el `auto` por defecto) impide que un valor largo ensanche la grilla.
const COLUMN_CLASSES = {
    1: '',
    2: 'md:grid-cols-2',
    3: 'md:grid-cols-2 lg:grid-cols-3',
    4: 'md:grid-cols-2 xl:grid-cols-4',
} as const;

/**
 * Ficha de detalle (DESIGN.md §9): pares etiqueta-dato en un `<dl>`.
 */
export function DetailList({
    columns = 2,
    children,
}: {
    columns?: keyof typeof COLUMN_CLASSES;
    children: ReactNode;
}) {
    return (
        <dl className={cn('grid grid-cols-1 gap-4', COLUMN_CLASSES[columns])}>
            {children}
        </dl>
    );
}

/**
 * La etiqueta de un par etiqueta-dato: única fuente de su clase. La usan `<DetailItem>` y la tarjeta del celular de
 * `<DataListing>`.
 */
export function DetailLabel({ children }: { children: ReactNode }) {
    return (
        <dt className="text-xs font-medium text-muted-foreground">
            {children}
        </dt>
    );
}

/**
 * Un par etiqueta-dato, con la etiqueta encima. Sin dato pinta `<EmptyCell />` y no quita el par: en una ficha los
 * campos son fijos y «—» dice que falta. Si un campo solo existe a veces, lo condiciona la pantalla.
 *
 * Recibe `value` y no `children`: dos nodos en `children` llegan como arreglo y `isEmptyCell` no los vería vacíos.
 */
export function DetailItem({
    label,
    value,
    span,
    valueClassName,
}: {
    label: string;
    value: ReactNode;
    span?: 'full';
    valueClassName?: string;
}) {
    return (
        <div className={cn('min-w-0', span === 'full' && 'col-span-full')}>
            <DetailLabel>{label}</DetailLabel>
            <dd
                className={cn(
                    'mt-1 text-sm break-words text-foreground',
                    valueClassName,
                )}
            >
                {isEmptyCell(value) ? <EmptyCell /> : value}
            </dd>
        </div>
    );
}
