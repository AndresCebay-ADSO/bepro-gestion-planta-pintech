import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { MoreHorizontal } from 'lucide-react';
import { useId } from 'react';

import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { visibleRowActions } from '@/lib/data-listing';
import { cn } from '@/lib/utils';

/** Una acción del menú «⋯»: o navega (`href`) o ejecuta un manejador (`onSelect`), nunca las dos. */
export type RowAction = {
    /** «Editar», «Desactivar», «Eliminar», «Reactivar»… */
    label: string;
    icon?: LucideIcon;
    /** Texto en rojo: solo «Eliminar». Va al final, tras un separador. */
    destructive?: boolean;
    /** Si existe, la acción se muestra bloqueada con este motivo debajo. */
    disabledReason?: string;
    /** Oculta la acción (por permiso). */
    hidden?: boolean;
} & (
    { onSelect: () => void; href?: never } | { href: string; onSelect?: never }
);

type RowActionsProps = {
    actions: RowAction[];
    /** Nombre del registro, para el texto del lector de pantalla: «Acciones de MP-001». */
    itemLabel?: string;
    className?: string;
};

/**
 * Menú «⋯» de las acciones secundarias de una fila (DESIGN.md §9). Sin acciones visibles no se pinta nada.
 */
export function RowActions({ actions, itemLabel, className }: RowActionsProps) {
    const { regular, destructive } = visibleRowActions(actions);

    if (regular.length === 0 && destructive.length === 0) {
        return null;
    }

    const triggerLabel = itemLabel ? `Acciones de ${itemLabel}` : 'Acciones';

    return (
        // No modal: una acción puede abrir un diálogo (eliminar) y el menú modal le dejaría bloqueado el puntero.
        // Sin tooltip (excepción de DESIGN.md §6): al cerrar el menú el foco vuelve al botón y lo dejaría abierto.
        <DropdownMenu modal={false}>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className={cn('size-8', className)}
                >
                    <MoreHorizontal className="size-4" />
                    <span className="sr-only">{triggerLabel}</span>
                </Button>
            </DropdownMenuTrigger>

            <DropdownMenuContent align="end" className="min-w-44">
                {regular.map((action) => (
                    <RowActionItem key={action.label} action={action} />
                ))}
                {regular.length > 0 && destructive.length > 0 && (
                    <DropdownMenuSeparator />
                )}
                {destructive.map((action) => (
                    <RowActionItem key={action.label} action={action} />
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

function RowActionItem({ action }: { action: RowAction }) {
    const reasonId = useId();
    const Icon = action.icon;
    const variant = action.destructive ? 'destructive' : 'default';

    // Bloqueada: no se usa `disabled` de Radix (el ítem perdería el foco y el lector de pantalla se lo saltaría).
    // Se anuncia con aria-disabled, no ejecuta nada, no navega aunque tenga `href` y deja el menú abierto.
    if (action.disabledReason) {
        return (
            <DropdownMenuItem
                variant={variant}
                aria-disabled="true"
                aria-describedby={reasonId}
                className="cursor-not-allowed items-start"
                onSelect={(event) => event.preventDefault()}
            >
                {Icon && <Icon className="mt-0.5 opacity-50" />}
                <span className="flex flex-col gap-0.5">
                    <span className="opacity-50">{action.label}</span>
                    <span
                        id={reasonId}
                        className="text-xs text-muted-foreground"
                    >
                        {action.disabledReason}
                    </span>
                </span>
            </DropdownMenuItem>
        );
    }

    if (action.href !== undefined) {
        return (
            <DropdownMenuItem variant={variant} asChild>
                <Link href={action.href}>
                    {Icon && <Icon />}
                    {action.label}
                </Link>
            </DropdownMenuItem>
        );
    }

    return (
        <DropdownMenuItem variant={variant} onSelect={action.onSelect}>
            {Icon && <Icon />}
            {action.label}
        </DropdownMenuItem>
    );
}
