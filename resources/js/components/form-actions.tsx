import { Link } from '@inertiajs/react';

import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type FormActionsProps = {
    /** Texto del botón principal: «Registrar entrada», «Guardar cliente». */
    submitLabel: string;
    /** Mientras guarda, el botón principal se deshabilita y muestra la rueda. */
    processing?: boolean;
    /** «Cancelar» en un cajón o una ventana: la cierra. */
    onCancel?: () => void;
    /** «Cancelar» en una página: vuelve al listado. */
    cancelHref?: string;
    cancelLabel?: string;
    className?: string;
};

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
    className,
}: FormActionsProps) {
    const hasCancel = onCancel !== undefined || cancelHref !== undefined;

    return (
        <div
            data-slot="form-actions"
            className={cn(
                'flex w-full flex-col-reverse gap-2 sm:flex-row sm:items-center',
                hasCancel ? 'sm:justify-between' : 'sm:justify-end',
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
            <Button type="submit" disabled={processing}>
                {processing && <Spinner />}
                {submitLabel}
            </Button>
        </div>
    );
}
