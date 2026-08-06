import { cn } from '@/lib/utils';

type Props = {
    status: string;
    label: string;
    className?: string;
};

const statusClasses: Record<string, string> = {
    draft: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-300',
    submitted:
        'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400',
    in_review:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400',
    approved:
        'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
    rejected: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400',
};

export default function StatusBadge({ status, label, className }: Props) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                statusClasses[status] ?? 'bg-gray-100 text-gray-800',
                className,
            )}
        >
            {label}
        </span>
    );
}
