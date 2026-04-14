import { Moon, Sun } from 'lucide-react';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type ThemeToggleButtonProps = {
    variant?: 'default' | 'fab';
    className?: string;
};

export default function ThemeToggleButton({
    variant = 'default',
    className,
}: ThemeToggleButtonProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const isDark = resolvedAppearance === 'dark';
    const nextMode = isDark ? 'light' : 'dark';

    return (
        <button
            type="button"
            onClick={() => updateAppearance(nextMode)}
            aria-label={`Activar modo ${nextMode === 'dark' ? 'oscuro' : 'claro'}`}
            className={cn(
                'relative inline-flex cursor-pointer items-center justify-center rounded-full transition-colors',
                variant === 'fab'
                    ? 'size-14 bg-primary text-primary-foreground hover:bg-primary/90'
                    : 'h-11 w-11 border border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground',
                className,
            )}
        >
            {isDark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </button>
    );
}
