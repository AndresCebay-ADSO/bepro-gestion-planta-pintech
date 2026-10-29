import type { ReactNode } from 'react';

/**
 * Tabla sin filas (DESIGN.md §9): el mensaje centrado en una caja punteada. La usan `<DataListing>` y
 * `<DetailTable>`.
 */
export function EmptyState({ children }: { children: ReactNode }) {
    return (
        <div className="rounded-lg border border-dashed border-border px-4 py-12 text-center text-sm text-muted-foreground">
            {children}
        </div>
    );
}
