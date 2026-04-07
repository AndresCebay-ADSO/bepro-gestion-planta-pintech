import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { HTMLAttributes } from 'react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type Props = HTMLAttributes<HTMLDivElement> & {
    compact?: boolean;
};

export default function AppearanceToggleTab({
    className = '',
    compact = false,
    ...props
}: Props) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Claro' },
        { value: 'dark', icon: Moon, label: 'Oscuro' },
        { value: 'system', icon: Monitor, label: 'Sistema' },
    ];

    return (
        <div
            className={cn(
                'inline-flex gap-1 rounded-lg bg-neutral-100 p-1 dark:bg-neutral-800',
                className,
            )}
            {...props}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <button
                    key={value}
                    onClick={() => updateAppearance(value)}
                    aria-label={`Tema ${label.toLowerCase()}`}
                    className={cn(
                        'flex items-center rounded-md transition-colors',
                        compact ? 'px-2 py-1.5' : 'px-3.5 py-1.5',
                        appearance === value
                            ? 'bg-white shadow-xs dark:bg-neutral-700 dark:text-neutral-100'
                            : 'text-neutral-500 hover:bg-neutral-200/60 hover:text-black dark:text-neutral-400 dark:hover:bg-neutral-700/60',
                    )}
                >
                    <Icon className={cn('h-4 w-4', !compact && '-ml-1')} />
                    {!compact && <span className="ml-1.5 text-sm">{label}</span>}
                </button>
            ))}
        </div>
    );
}
