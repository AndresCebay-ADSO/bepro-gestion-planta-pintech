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
                'inline-flex gap-1 rounded-lg border border-border bg-muted/60 p-1',
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
                            ? 'bg-background text-foreground shadow-xs'
                            : 'text-muted-foreground hover:bg-accent/80 hover:text-foreground',
                    )}
                >
                    <Icon className={cn('h-4 w-4', !compact && '-ml-1')} />
                    {!compact && (
                        <span className="ml-1.5 text-sm">{label}</span>
                    )}
                </button>
            ))}
        </div>
    );
}
