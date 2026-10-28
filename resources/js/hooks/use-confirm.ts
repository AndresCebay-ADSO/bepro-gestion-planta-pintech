import { createContext, useContext } from 'react';
import type { ReactNode } from 'react';

export type ConfirmOptions = {
    /** La pregunta: «¿Eliminar este cliente?». */
    title: string;
    /** Qué pasa si se confirma, o por qué puede fallar. */
    description?: ReactNode;
    /** Texto del botón de la acción: «Eliminar», «Desactivar». Por defecto, «Confirmar». */
    confirmLabel?: string;
    cancelLabel?: string;
    /** Acción que borra o anula algo: el botón va en rojo. */
    destructive?: boolean;
};

export type ConfirmFn = (options: ConfirmOptions) => Promise<boolean>;

export const ConfirmContext = createContext<ConfirmFn | null>(null);

/**
 * Pide confirmación con el diálogo de la app (`ConfirmDialog`), en lugar del `confirm()` del navegador. Devuelve una
 * promesa: `true` si el usuario confirma, `false` si cancela o cierra el diálogo.
 *
 * ```tsx
 * const confirm = useConfirm();
 *
 * if (await confirm({ title: '¿Eliminar este cliente?', confirmLabel: 'Eliminar', destructive: true })) {
 *     router.delete(clientsDestroy(client.id).url);
 * }
 * ```
 */
export function useConfirm(): ConfirmFn {
    const confirm = useContext(ConfirmContext);

    if (confirm === null) {
        throw new Error(
            'useConfirm necesita ConfirmDialogProvider (app.tsx) más arriba.',
        );
    }

    return confirm;
}
