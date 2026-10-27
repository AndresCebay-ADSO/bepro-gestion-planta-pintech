/**
 * Título de sección: siempre `h2`, porque cuelga del único `h1` de la página (`<PageHeader>`). `small` solo cambia el
 * tamaño, no el nivel: un salto de `h1` a `h3` desorienta a quien navega con lector de pantalla.
 */
export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className="space-y-0.5">
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-base font-semibold'
                        : 'text-lg font-semibold'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
