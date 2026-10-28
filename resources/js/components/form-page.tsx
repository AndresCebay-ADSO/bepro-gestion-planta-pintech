import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

type FormPageProps = {
    /**
     * `narrow` (`max-w-3xl`): formularios sin líneas ni matrices (cliente, materia prima, bodega, usuario, producto).
     * `wide` (`max-w-5xl`): con líneas o una matriz (OP, fórmula, cotización, pedido, rol, I+D).
     */
    width: 'narrow' | 'wide';
    className?: string;
    children: ReactNode;
};

/** Marco de una página de formulario (DESIGN.md §8): centrado, con uno de los dos anchos y el relleno de las pantallas. */
export function FormPage({ width, className, children }: FormPageProps) {
    return (
        <div
            data-slot="form-page"
            className={cn(
                'mx-auto w-full space-y-6 p-6',
                width === 'wide' ? 'max-w-5xl' : 'max-w-3xl',
                className,
            )}
        >
            {children}
        </div>
    );
}
