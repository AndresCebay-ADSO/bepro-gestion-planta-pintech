import { Link } from '@inertiajs/react';
import type { ComponentProps } from 'react';

import { cn } from '@/lib/utils';

/** Enlace a la ficha de un registro: el identificador de un listado o un dato que lleva a otro módulo. */
export function RecordLink({
    className,
    ...props
}: ComponentProps<typeof Link>) {
    return (
        <Link
            className={cn(
                'rounded-sm text-primary underline-offset-4 hover:underline focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                className,
            )}
            {...props}
        />
    );
}
