import type { ReactNode } from 'react';
import { useCallback, useState } from 'react';

import { EmptyCell } from '@/components/empty-cell';
import { EmptyState } from '@/components/empty-state';
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { isEmptyCell } from '@/lib/data-listing';
import { cn } from '@/lib/utils';

export type DetailTableColumn<T> = {
    key: string;
    header: string;
    /** Sin dato, `null` (o `undefined`, `false`, `''`): la celda muestra «—». */
    cell: (row: T) => ReactNode;
    /** Cifras a la derecha (con `tabular-nums`); estados al centro. El texto (a la izquierda) salta de línea. */
    align?: 'left' | 'center' | 'right';
    /** Clases de la celda (p. ej. `font-mono` en un código). */
    className?: string;
};

type DetailTableProps<T> = {
    rows: T[];
    rowKey: (row: T) => string | number;
    columns: DetailTableColumn<T>[];
    /** Mensaje cuando no hay filas. */
    empty: ReactNode;
    /** Qué contiene la tabla, solo para el lector de pantalla. */
    caption?: string;
};

// Las cifras y las pastillas no saltan de línea; el texto sí, con un ancho mínimo para que no quede una palabra
// por línea.
const alignClasses = {
    left: 'min-w-32 text-left whitespace-normal break-words',
    center: 'text-center',
    right: 'text-right tabular-nums',
} as const;

const headAlignClasses = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right',
} as const;

/**
 * Tabla de las líneas de un registro (DESIGN.md §9): los productos de un pedido, los ingredientes de una fórmula.
 * No es un listado: en el celular sigue siendo tabla, se desplaza a lo ancho dentro de su caja y la primera columna
 * (la que dice qué es la línea) queda fija.
 */
export function DetailTable<T>({
    rows,
    rowKey,
    columns,
    empty,
    caption,
}: DetailTableProps<T>) {
    // La línea a la derecha de la columna fija solo sale cuando la tabla no cabe en su caja y se puede desplazar.
    const [overflows, setOverflows] = useState(false);
    const tableRef = useCallback((table: HTMLTableElement | null) => {
        // La caja que se desplaza es el contenedor que pone `<Table>` alrededor de la tabla.
        const box = table?.parentElement;

        if (!table || !box) {
            return;
        }

        const observer = new ResizeObserver(() =>
            setOverflows(box.scrollWidth > box.clientWidth),
        );
        observer.observe(box);
        observer.observe(table);

        return () => observer.disconnect();
    }, []);

    if (rows.length === 0) {
        return <EmptyState>{empty}</EmptyState>;
    }

    // Columna fija: necesita fondo propio (lo de atrás pasa por debajo al desplazar). `border-separate`: con
    // bordes colapsados, los bordes son de la tabla y no se mueven con la celda fija.
    const sticky = cn(
        'sticky left-0 z-10 border-r',
        overflows ? 'border-r-border' : 'border-r-transparent',
    );

    return (
        <div className="overflow-hidden rounded-lg border border-border bg-card">
            <Table ref={tableRef} className="border-separate border-spacing-0">
                {caption && (
                    <TableCaption className="sr-only">{caption}</TableCaption>
                )}
                <TableHeader>
                    <TableRow>
                        {columns.map((column, index) => (
                            <TableHead
                                key={column.key}
                                className={cn(
                                    'border-b',
                                    headAlignClasses[column.align ?? 'left'],
                                    // El mismo tono del encabezado, opaco: el fondo de la tarjeta con el gris encima.
                                    index === 0 &&
                                        cn(
                                            sticky,
                                            'bg-card bg-linear-to-r from-muted/40 to-muted/40',
                                        ),
                                )}
                            >
                                {column.header}
                            </TableHead>
                        ))}
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {rows.map((row, rowIndex) => (
                        // Sin resaltado al pasar el mouse: la fila no abre nada y la columna fija no lo seguiría.
                        <TableRow
                            key={rowKey(row)}
                            className="hover:bg-transparent"
                        >
                            {columns.map((column, index) => {
                                const value = column.cell(row);

                                return (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            rowIndex < rows.length - 1 &&
                                                'border-b',
                                            alignClasses[
                                                column.align ?? 'left'
                                            ],
                                            index === 0 &&
                                                cn(
                                                    sticky,
                                                    'bg-card font-medium',
                                                ),
                                            column.className,
                                        )}
                                    >
                                        {isEmptyCell(value) ? (
                                            <EmptyCell />
                                        ) : (
                                            value
                                        )}
                                    </TableCell>
                                );
                            })}
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
        </div>
    );
}
