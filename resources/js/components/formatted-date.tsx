import { format } from 'date-fns';
import { es } from 'date-fns/locale';

interface FormattedDateProps {
    value: string | null | undefined;
    format?: 'short' | 'long' | 'datetime' | 'date';
    emptyValue?: string;
}

const formatPatterns = {
    short: 'dd MMM yyyy',      // 15 ene 2024
    long: 'dd MMMM yyyy',      // 15 enero 2024
    datetime: 'dd MMM yyyy, HH:mm',  // 15 ene 2024, 10:30
    date: 'dd/MM/yyyy',        // 15/01/2024
};

export function FormattedDate({
    value,
    format: formatType = 'short',
    emptyValue = '-',
}: FormattedDateProps) {
    if (!value) {
        return <span>{emptyValue}</span>;
    }

    try {
        const date = new Date(value);
        const pattern = formatPatterns[formatType];
        const formatted = format(date, pattern, { locale: es });
        return <span>{formatted}</span>;
    } catch {
        return <span>{value}</span>;
    }
}
