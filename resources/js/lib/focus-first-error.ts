/**
 * Después de un envío con errores del servidor, lleva el foco al primer campo con error (auditoría 3.6, INV-11). Sin
 * esto el foco se queda donde estaba y, en un cajón o en un formulario largo, el error puede quedar fuera de vista.
 *
 * Es una sola función para todos los formularios: busca el campo por el `id` o el `name` que tenga igual a la llave del
 * error (`quantity`, `items.2.quantity`). Si no lo encuentra, lleva a la vista el primer mensaje de error.
 */

type ErrorBag = Partial<Record<string, string | undefined>>;

/** Las llaves con un mensaje, en el orden en que llegaron del servidor. */
export function orderedErrorKeys(errors: ErrorBag): string[] {
    return Object.entries(errors)
        .filter(
            ([, message]) =>
                typeof message === 'string' && message.trim() !== '',
        )
        .map(([key]) => key);
}

/**
 * Selectores donde buscar el campo de una llave. Una llave de fila (`items.2.quantity`) busca primero el campo exacto y
 * después el que contiene la lista (`items`).
 */
export function errorSelectorsFor(key: string): string[] {
    const escaped = key.replace(/["\\]/g, '\\$&');
    const selectors = [`[id="${escaped}"]`, `[name="${escaped}"]`];

    if (key.includes('.')) {
        const root = key.split('.')[0].replace(/["\\]/g, '\\$&');
        selectors.push(`[id="${root}"]`, `[name="${root}"]`);
    }

    return selectors;
}

/** Mensajes de error visibles que ningún campo reclamó (el resumen de errores, un aviso del diálogo). */
const FALLBACK_SELECTOR = '[role="alert"], p.text-destructive';

export function focusFirstError(
    errors: ErrorBag,
    root: Document = document,
): boolean {
    for (const key of orderedErrorKeys(errors)) {
        for (const selector of errorSelectorsFor(key)) {
            const element = root.querySelector<HTMLElement>(selector);

            if (element === null || element.hasAttribute('disabled')) {
                continue;
            }

            element.scrollIntoView({ block: 'center', behavior: 'smooth' });
            element.focus({ preventScroll: true });

            return true;
        }
    }

    const message = root.querySelector<HTMLElement>(FALLBACK_SELECTOR);

    if (message === null) {
        return false;
    }

    message.scrollIntoView({ block: 'center', behavior: 'smooth' });

    // El campo cuyo error es ese mensaje: el control que comparte su contenedor (la mayoría de los formularios ponen el
    // campo y su error juntos), aunque su `id` no coincida con la llave del error.
    const field = message.parentElement?.querySelector<HTMLElement>(
        'input:not([type="hidden"]), textarea, select, [role="combobox"]',
    );

    if (field && !field.hasAttribute('disabled')) {
        field.focus({ preventScroll: true });
    }

    return true;
}
