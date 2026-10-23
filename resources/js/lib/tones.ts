import type { Tone } from '@/types/enums';

/*
 * El único mapa tono → clases (DESIGN.md §4). Ninguna pantalla decide el color de un estado: lo da el enum en PHP
 * (`tone()`) y se pinta con `<StatusBadge>`, `<ActiveBadge>` o `<PriorityBadge>`. Para cambiar un color, se cambia el
 * token en `app.css`, no este mapa.
 */
export const toneClasses: Record<Tone, string> = {
    neutral: 'bg-muted text-muted-foreground',
    info: 'bg-info/15 text-info-text',
    warning: 'bg-warning/15 text-warning-text',
    success: 'bg-success/15 text-success-text',
    danger: 'bg-destructive/15 text-destructive-text',
};

/** Forma única de la pastilla. `gap-1` deja sitio a un icono opcional; `whitespace-nowrap` la mantiene en una línea. */
export const pillClasses =
    'inline-flex items-center gap-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-medium';
