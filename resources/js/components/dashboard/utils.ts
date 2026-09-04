import { formatSafeDate } from '@/components/formatted-date';

export function formatBackendDate(dateString: string | null): string {
    return formatSafeDate(dateString, 'short', '');
}
