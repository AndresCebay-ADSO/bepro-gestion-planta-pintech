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
                'relative inline-flex items-center justify-center rounded-full transition-colors cursor-pointer',
                variant === 'fab'
                    ? 'bg-primary text-primary-foreground hover:bg-primary/90 size-14'
                    : 'border-input bg-background text-muted-foreground hover:bg-accent hover:text-foreground h-11 w-11 border ',
                className,
            )}
        >
            {isDark ? <Sun className="size-5" /> : <Moon className="size-5" />}
        </button>
    );
}
