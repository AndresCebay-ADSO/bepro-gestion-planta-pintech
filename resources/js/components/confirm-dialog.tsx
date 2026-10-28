import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef, useState } from 'react';
import type { ReactNode } from 'react';

import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import { ConfirmContext } from '@/hooks/use-confirm';
import type { ConfirmOptions } from '@/hooks/use-confirm';

type ConfirmDialogProps = ConfirmOptions & {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onConfirm: () => void;
};

/**
 * Diálogo de confirmación de la app (DESIGN.md §8), sobre `ui/alert-dialog.tsx`. «Cancelar» a la izquierda y la acción a
 * la derecha; en rojo si es destructiva. Para pedir confirmación desde un manejador, `useConfirm()`; este componente
 * sirve cuando el diálogo se controla con estado propio.
 */
export function ConfirmDialog({
    open,
    onOpenChange,
    onConfirm,
    title,
    description,
    confirmLabel = 'Confirmar',
    cancelLabel = 'Cancelar',
    destructive = false,
}: ConfirmDialogProps) {
    return (
        <AlertDialog open={open} onOpenChange={onOpenChange}>
            {/* Sin descripción, Radix avisa que falta: se le dice que no la hay. */}
            <AlertDialogContent
                {...(description ? {} : { 'aria-describedby': undefined })}
            >
                <AlertDialogHeader>
                    <AlertDialogTitle>{title}</AlertDialogTitle>
                    {description && (
                        <AlertDialogDescription>
                            {description}
                        </AlertDialogDescription>
                    )}
                </AlertDialogHeader>
                <AlertDialogFooter className="sm:justify-between">
                    <AlertDialogCancel>{cancelLabel}</AlertDialogCancel>
                    <AlertDialogAction
                        variant={destructive ? 'destructive' : 'default'}
                        onClick={onConfirm}
                    >
                        {confirmLabel}
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

/**
 * Monta un único `ConfirmDialog` para toda la app y da `useConfirm()` a las pantallas. Va en `app.tsx`.
 */
export function ConfirmDialogProvider({ children }: { children: ReactNode }) {
    const [options, setOptions] = useState<ConfirmOptions | null>(null);
    const [open, setOpen] = useState(false);
    // La promesa se resuelve una sola vez: con la acción, o con `false` al cerrar de cualquier forma.
    const resolveRef = useRef<((confirmed: boolean) => void) | null>(null);

    const settle = useCallback((confirmed: boolean) => {
        resolveRef.current?.(confirmed);
        resolveRef.current = null;
        setOpen(false);
    }, []);

    const confirm = useCallback(
        (next: ConfirmOptions) =>
            new Promise<boolean>((resolve) => {
                // Una confirmación nueva antes de responder la anterior la da por cancelada.
                resolveRef.current?.(false);
                resolveRef.current = resolve;
                setOptions(next);
                setOpen(true);
            }),
        [],
    );

    // Al irse de la pantalla (un enlace, el botón atrás), la pregunta abierta queda sin respuesta: se da por cancelada.
    // `router.on` devuelve la función que quita el oyente: es la limpieza del efecto.
    useEffect(
        () =>
            router.on('before', () => {
                settle(false);
            }),
        [settle],
    );

    return (
        <ConfirmContext.Provider value={confirm}>
            {children}
            {options && (
                <ConfirmDialog
                    {...options}
                    open={open}
                    onOpenChange={(next) => {
                        if (!next) {
                            settle(false);
                        }
                    }}
                    onConfirm={() => settle(true)}
                />
            )}
        </ConfirmContext.Provider>
    );
}
