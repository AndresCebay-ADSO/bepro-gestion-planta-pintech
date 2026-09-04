import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export interface FormattedDateProps {
    value: string | null | undefined;
    format?: 'short' | 'long' | 'datetime' | 'date';
    emptyValue?: string;
}

const formatPatterns = {
    short: 'dd MMM yyyy', // 15 ene 2024
    long: 'dd MMMM yyyy', // 15 enero 2024
    datetime: 'dd MMM yyyy, HH:mm', // 15 ene 2024, 10:30
    date: 'dd/MM/yyyy', // 15/01/2024
};

/**
 * Parsea de manera segura fechas ISO o cadenas YYYY-MM-DD sin desfase de zona horaria.
 */
export function parseSafeDate(value: string | null | undefined): Date | null {
    if (!value) {
        return null;
    }

    const trimmed = value.trim();

    // 1. Fecha de negocio pura: YYYY-MM-DD
    const dateOnlyMatch = trimmed.match(/^(\d{4})-(\d{2})-(\d{2})$/);

    if (dateOnlyMatch) {
        const year = Number(dateOnlyMatch[1]);
        const month = Number(dateOnlyMatch[2]) - 1; // 0-indexed
        const day = Number(dateOnlyMatch[3]);

        return new Date(year, month, day);
    }

    // 2. Fecha con espacio: YYYY-MM-DD HH:mm:ss
    const dateTimeSpaceMatch = trimmed.match(
        /^(\d{4})-(\d{2})-(\d{2})\s+(\d{2}):(\d{2})(?::(\d{2}))?$/,
    );

    if (dateTimeSpaceMatch) {
        const year = Number(dateTimeSpaceMatch[1]);
        const month = Number(dateTimeSpaceMatch[2]) - 1;
        const day = Number(dateTimeSpaceMatch[3]);
        const hours = Number(dateTimeSpaceMatch[4]);
        const minutes = Number(dateTimeSpaceMatch[5]);
        const seconds = Number(dateTimeSpaceMatch[6] ?? '0');

        return new Date(year, month, day, hours, minutes, seconds);
    }

    // 3. Timestamp ISO 8601 (con T o Z)
    const date = new Date(trimmed);

    if (isNaN(date.getTime())) {
        return null;
    }

    return date;
}

export function formatSafeDate(
    value: string | null | undefined,
    formatType: FormattedDateProps['format'] = 'short',
    emptyValue = '-',
): string {
    if (!value) {
        return emptyValue;
    }

    try {
        const date = parseSafeDate(value);

        if (!date) {
            return emptyValue;
        }

        const pattern = formatPatterns[formatType ?? 'short'];

        return format(date, pattern, { locale: es });
    } catch {
        return value;
    }
}

export function FormattedDate({
    value,
    format: formatType = 'short',
    emptyValue = '-',
}: FormattedDateProps) {
    if (!value) {
        return <span>{emptyValue}</span>;
    }

    const formattedValue = formatSafeDate(value, formatType, emptyValue);

    return <span>{formattedValue}</span>;
}
