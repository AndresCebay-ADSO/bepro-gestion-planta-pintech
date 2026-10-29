import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type FormActionsProps = {
    /** Mientras guarda, el botón principal se deshabilita y muestra la rueda. */
    processing?: boolean;
    /** «Cancelar» en un cajón o una ventana: la cierra. */
    onCancel?: () => void;
    /** «Cancelar» en una página: vuelve al listado. */
    cancelHref?: string;
    cancelLabel?: string;
    /** El botón principal en rojo: la acción destruye o anula algo (cancelar una orden). */
    destructive?: boolean;
    /**
     * Botones a todo el ancho y uno debajo del otro también en el computador: las pantallas de acceso, cuya tarjeta
     * es angosta.
     */
    fullWidth?: boolean;
    className?: string;
} & (
    | {
          /** Texto del botón principal: «Registrar entrada», «Guardar cliente». */
          submitLabel: string;
          children?: never;
      }
    | {
          submitLabel?: never;
          /**
           * Botones propios a la derecha, en lugar del botón principal: los pasos de un formulario largo («Anterior»,
           * «Continuar», «Guardar borrador», «Enviar»). El último es el principal: en el celular queda arriba.
           */
          children: ReactNode;
      }
);

/**
 * Pie de un formulario (DESIGN.md §8): «Cancelar» a la izquierda y la acción principal a la derecha, con el botón
 * primario (nunca colores directos). En el celular se apilan con la acción principal arriba. En un cajón o una ventana
 * va dentro de `SheetFooter`/`DialogFooter`, que lo deja fijo abajo mientras los campos se desplazan.
 */
export function FormActions({
    submitLabel,
    processing = false,
    onCancel,
    cancelHref,
    cancelLabel = 'Cancelar',
    destructive = false,
    fullWidth = false,
    className,
    children,
}: FormActionsProps) {
    const hasCancel = onCancel !== undefined || cancelHref !== undefined;

    return (
        <div
            data-slot="form-actions"
            className={cn(
                'flex w-full flex-col-reverse gap-2',
                !fullWidth && 'sm:flex-row sm:items-center',
                !fullWidth &&
                    (hasCancel ? 'sm:justify-between' : 'sm:justify-end'),
                className,
            )}
        >
            {cancelHref !== undefined ? (
                <Button variant="outline" asChild>
                    <Link href={cancelHref}>{cancelLabel}</Link>
                </Button>
            ) : onCancel !== undefined ? (
                <Button type="button" variant="outline" onClick={onCancel}>
                    {cancelLabel}
                </Button>
            ) : null}
            {children !== undefined ? (
                <div className="flex flex-col-reverse gap-2 sm:flex-row sm:items-center">
                    {children}
                </div>
            ) : (
                <Button
                    type="submit"
                    variant={destructive ? 'destructive' : 'default'}
                    disabled={processing}
                >
                    {processing && <Spinner />}
                    {submitLabel}
                </Button>
            )}
        </div>
    );
}
