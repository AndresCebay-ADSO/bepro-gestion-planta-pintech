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
