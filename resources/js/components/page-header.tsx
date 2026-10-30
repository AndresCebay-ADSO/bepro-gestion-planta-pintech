import type { ReactNode } from 'react';

type PageHeaderProps = {
    title: ReactNode;
    /** Una línea bajo el título que explica la pantalla. */
    description?: ReactNode;
    /** Insignia junto al título (estado, activo/inactivo). */
    badge?: ReactNode;
    /** Botones de la pantalla: a la derecha en escritorio, bajo el título en el celular. */
    actions?: ReactNode;
    /** Líneas extra bajo la descripción. */
    children?: ReactNode;
};

/**
 * Título de página (el único `h1` de cada pantalla), con la escala de DESIGN.md §5. Desde `md`, las acciones van a la
 * derecha mientras el título conserve unos 20rem; si no caben, pasan a su propia línea en vez de aplastarlo.
 */
export function PageHeader({
    title,
    description,
    badge,
    actions,
    children,
}: PageHeaderProps) {
    return (
        <div className="flex flex-col gap-3 md:flex-row md:flex-wrap md:items-start md:justify-between">
            <div className="min-w-0 space-y-1 md:flex-1 md:basis-80">
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                    <h1 className="min-w-0 text-2xl font-semibold tracking-tight wrap-anywhere text-foreground">
                        {title}
                    </h1>
                    {badge}
                </div>
                {description && (
                    <div className="text-sm text-muted-foreground">
                        {description}
                    </div>
                )}
                {children}
            </div>

            {actions && (
                <div className="flex flex-wrap items-center gap-2">
                    {actions}
                </div>
            )}
        </div>
    );
}
