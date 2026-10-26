import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { useEffect } from 'react';

import { RowActions } from '@/components/row-actions';
import type { RowAction } from '@/components/row-actions';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { listingColumnProblems, visibleRowActions } from '@/lib/data-listing';
import type { MobileRole } from '@/lib/data-listing';
import { cn } from '@/lib/utils';

export type DataListingColumn<T> = {
    key: string;
    header: string;
    cell: (row: T) => ReactNode;
    /**
     * Papel en la tarjeta del celular (DESIGN.md §9): `title` el identificador, `subtitle` lo que lo describe,
     * `status` la pastilla, `meta` cantidades, fechas y totales (máximo 4), `hidden` no aparece.
     */
    mobile: MobileRole;
    /** Cifras a la derecha (con `tabular-nums`); estados y alertas al centro. */
    align?: 'left' | 'center' | 'right';
    /** Clases de la celda en la tabla del computador (p. ej. `max-w-xs truncate`). No llegan a la tarjeta. */
    className?: string;
    /** Clases del valor en la tarjeta del celular (p. ej. `font-mono`). */
    cardClassName?: string;
};

type DataListingProps<T> = {
    rows: T[];
    rowKey: (row: T) => string | number;
    columns: DataListingColumn<T>[];
    /** Detalle (o edición, si no hay detalle) al que lleva el identificador. Sin permiso, `undefined`: texto normal. */
    rowHref?: (row: T) => string | undefined;
    /** Acciones del menú «⋯». Sin acciones visibles en ninguna fila, no hay columna de acciones. */
    actions?: (row: T) => RowAction[];
    /** Nombre del registro para el lector de pantalla («Acciones de MP-001»). */
    rowLabel?: (row: T) => string;
    /** Mensaje cuando no hay filas. */
    empty: ReactNode;
};

const alignClasses = {
    left: 'text-left',
    center: 'text-center',
    right: 'text-right tabular-nums',
} as const;

/**
 * Listado único (DESIGN.md §9): las mismas columnas pintan la tabla en el computador y las tarjetas en el celular.
 */
export function DataListing<T>({
    rows,
    rowKey,
    columns,
    rowHref,
    actions,
    rowLabel,
    empty,
}: DataListingProps<T>) {
    // Firma estable (clave y papel): `columns` es un arreglo nuevo en cada render y el aviso se repetiría.
    // Solo en desarrollo: en producción no se calcula nada.
    const columnSignature = import.meta.env.DEV
        ? JSON.stringify(columns.map(({ key, mobile }) => [key, mobile]))
        : '';

    useEffect(() => {
        if (!import.meta.env.DEV) {
            return;
        }

        const problems = listingColumnProblems(
            (JSON.parse(columnSignature) as [string, MobileRole][]).map(
                ([key, mobile]) => ({ key, mobile }),
            ),
        );

        if (problems.length > 0) {
            console.warn(
                `DataListing: columnas mal definidas (DESIGN.md §9).\n- ${problems.join('\n- ')}`,
            );
        }
    }, [columnSignature]);

    if (rows.length === 0) {
        return (
            <div className="rounded-lg border border-dashed border-border px-4 py-12 text-center text-sm text-muted-foreground">
                {empty}
            </div>
        );
    }

    // Las acciones de cada fila se calculan una sola vez: sirven a la tabla, a la tarjeta y a la columna de acciones.
    const entries = rows.map((row) => ({
        row,
        key: rowKey(row),
        actions: actions ? actions(row) : [],
    }));
    const hasActions = entries.some(({ actions: rowActions }) => {
        const { regular, destructive } = visibleRowActions(rowActions);

        return regular.length + destructive.length > 0;
    });

    const titleColumn = columns.find((column) => column.mobile === 'title');
    const subtitleColumn = columns.find(
        (column) => column.mobile === 'subtitle',
    );
    const statusColumn = columns.find((column) => column.mobile === 'status');
    const metaColumns = columns.filter((column) => column.mobile === 'meta');

    // El identificador es el único enlace de la fila: el resto se puede seleccionar y copiar.
    const renderValue = (column: DataListingColumn<T>, row: T) => {
        const href = column.mobile === 'title' ? rowHref?.(row) : undefined;

        if (href === undefined) {
            return column.cell(row);
        }

        return (
            <Link
                href={href}
                className="rounded-sm font-medium text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                {column.cell(row)}
            </Link>
        );
    };

    return (
        <>
            {/* Computador: tabla; si es más ancha que su caja, se desplaza dentro de ella, nunca la página. */}
            <div className="hidden overflow-hidden rounded-lg border border-border bg-card md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((column) => (
                                <TableHead
                                    key={column.key}
                                    className={
                                        alignClasses[column.align ?? 'left']
                                    }
                                >
                                    {column.header}
                                </TableHead>
                            ))}
                            {hasActions && (
                                <TableHead className="w-12">
                                    <span className="sr-only">Acciones</span>
                                </TableHead>
                            )}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {entries.map(({ row, key, actions: rowActions }) => (
                            <TableRow key={key}>
                                {columns.map((column) => (
                                    <TableCell
                                        key={column.key}
                                        className={cn(
                                            alignClasses[
                                                column.align ?? 'left'
                                            ],
                                            column.mobile === 'title' &&
                                                'font-medium',
                                            column.className,
                                        )}
                                    >
                                        {renderValue(column, row)}
                                    </TableCell>
                                ))}
                                {hasActions && (
                                    <TableCell className="py-1 text-right">
                                        <RowActions
                                            actions={rowActions}
                                            itemLabel={rowLabel?.(row)}
                                        />
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {/* Celular: una tarjeta por fila. */}
            <ul className="space-y-3 md:hidden">
                {entries.map(({ row, key, actions: rowActions }) => (
                    <li
                        key={key}
                        className="rounded-lg border border-border bg-card p-4"
                    >
                        <div className="flex items-start justify-between gap-3">
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    {titleColumn && (
                                        <div
                                            className={cn(
                                                'min-w-0 font-medium break-words',
                                                titleColumn.cardClassName,
                                            )}
                                        >
                                            {renderValue(titleColumn, row)}
                                        </div>
                                    )}
                                    {statusColumn && (
                                        <div
                                            className={
                                                statusColumn.cardClassName
                                            }
                                        >
                                            {statusColumn.cell(row)}
                                        </div>
                                    )}
                                </div>
                                {subtitleColumn && (
                                    <div
                                        className={cn(
                                            'text-sm text-muted-foreground',
                                            subtitleColumn.cardClassName,
                                        )}
                                    >
                                        {subtitleColumn.cell(row)}
                                    </div>
                                )}
                            </div>
                            {hasActions && (
                                <RowActions
                                    actions={rowActions}
                                    itemLabel={rowLabel?.(row)}
                                    className="-mt-1 -mr-2 shrink-0"
                                />
                            )}
                        </div>

                        {metaColumns.length > 0 && (
                            <dl className="mt-3 grid grid-cols-2 gap-x-4 gap-y-2">
                                {metaColumns.map((column) => (
                                    <div key={column.key} className="min-w-0">
                                        <dt className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                            {column.header}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'text-sm break-words',
                                                column.align === 'right' &&
                                                    'tabular-nums',
                                                column.cardClassName,
                                            )}
                                        >
                                            {column.cell(row)}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        )}
                    </li>
                ))}
            </ul>
        </>
    );
}
