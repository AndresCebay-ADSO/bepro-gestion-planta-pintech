import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

const linkClassName =
    'rounded-sm text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/** Enlace a la ficha de un registro: el identificador de un listado o un dato que lleva a otro módulo. */
export function RecordLink({
    className,
    ...props
}: ComponentProps<typeof Link>) {
    return <Link className={cn(linkClassName, className)} {...props} />;
}

/** El identificador cuando lo que abre es un archivo: en otra pestaña, porque un PDF no es una página de Inertia. */
export function FileLink({ className, ...props }: ComponentProps<'a'>) {
    return (
        <a
            target="_blank"
            rel="noopener noreferrer"
            className={cn(linkClassName, className)}
            {...props}
        />
    );
}
