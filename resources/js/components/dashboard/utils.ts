import { format } from 'date-fns';
import { es } from 'date-fns/locale';

export function formatBackendDate(dateString: string | null): string {
    if (!dateString) {
return '';
}

    // Append T12:00:00 to avoid timezone shifts when creating a Date object from YYYY-MM-DD
    const [datePart] = dateString.split(' '); // safety measure for timestamps

    return format(new Date(`${datePart}T12:00:00`), 'dd MMM yyyy', { locale: es });
}
