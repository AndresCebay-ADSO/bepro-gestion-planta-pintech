/**
 * Mensajes de error del servidor para el resumen de un formulario: todos, en el orden en que llegan, sin vacíos ni
 * repetidos. Lista todos y no «los que no tienen campo»: una lista de claves ya mostradas sería otra copia que se
 * desincroniza cada vez que se agrega un campo (OPL-11).
 */
export function formErrorMessages(
    errors: Partial<Record<string, string | undefined>>,
): string[] {
    const messages = Object.values(errors).filter(
        (message): message is string =>
            typeof message === 'string' && message.trim() !== '',
    );

    return Array.from(new Set(messages));
}

/**
 * Las llaves de error de una lista de líneas (`items` → `items.0.quantity`, `items.2.color` y `items` mismo). Los
 * errores del servidor van por posición: al quitar una línea, los de las siguientes quedarían bajo la línea que se
 * corre a su número. Por eso, al quitar una línea se borran todos los de la lista (con `clearErrors`) y el usuario los
 * vuelve a ver al enviar.
 */
export function listErrorKeys(
    errors: Partial<Record<string, string | undefined>>,
    list: string,
): string[] {
    return Object.keys(errors).filter(
        (key) => key === list || key.startsWith(`${list}.`),
    );
}

/**
 * Al quitar una línea, borra los errores de su lista (`listErrorKeys`). Sin errores no llama a `clearErrors`: en
 * Inertia, `clearErrors()` sin argumentos borra los de todo el formulario.
 */
export function clearListErrors(
    errors: Partial<Record<string, string | undefined>>,
    // `never[]`: acepta el `clearErrors` de cualquier `useForm`, que es genérico en las llaves de su formulario.
    clearErrors: (...fields: never[]) => void,
    list: string,
): void {
    const keys = listErrorKeys(errors, list);

    if (keys.length > 0) {
        clearErrors(...(keys as never[]));
    }
}
