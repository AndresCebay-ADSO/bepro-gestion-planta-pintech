import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

type ThemeToggleButtonProps = {
    className?: string;
};

export default function ThemeToggleButton({
    className,
}: ThemeToggleButtonProps) {
    const { resolvedAppearance, updateAppearance } = useAppearance();

    const isDark = resolvedAppearance === 'dark';
    const nextMode = isDark ? 'light' : 'dark';
    const label = `Activar modo ${nextMode === 'dark' ? 'oscuro' : 'claro'}`;

    // Misma forma y medida que los demás botones del encabezado (plegar menú, campana).
    return (
        <Button
            variant="outline"
            size="icon"
            onClick={() => updateAppearance(nextMode)}
            aria-label={label}
            className={cn(
                'size-10 text-muted-foreground hover:text-foreground',
                className,
            )}
        >
            {isDark ? <Sun className="size-4" /> : <Moon className="size-4" />}
        </Button>
    );
}
