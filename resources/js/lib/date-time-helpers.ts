/**
 * Convierte una fecha ISO 8601 UTC al formato datetime-local del navegador.
 *
 * Ej: "2026-07-15T14:00:00Z" → "2026-07-15T09:00" (si el navegador está en UTC-5)
 */
export function toLocalDateTimeInput(
    isoUtcString: string | null | undefined,
): string {
    if (!isoUtcString) {
        return '';
    }

    const date = new Date(isoUtcString);

    if (isNaN(date.getTime())) {
        return '';
    }

    const pad = (n: number) => String(n).padStart(2, '0');
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());
    const hour = pad(date.getHours());
    const minute = pad(date.getMinutes());

    return `${year}-${month}-${day}T${hour}:${minute}`;
}

/**
 * Convierte un valor de input datetime-local a ISO 8601 UTC.
 *
 * Ej: "2026-07-15T09:00" → "2026-07-15T14:00:00Z" (si el navegador está en UTC-5)
 */
export function toUtcIsoString(localDateTimeString: string): string {
    if (!localDateTimeString) {
        return '';
    }

    const date = new Date(localDateTimeString);

    if (isNaN(date.getTime())) {
        return '';
    }

    return date.toISOString();
}
