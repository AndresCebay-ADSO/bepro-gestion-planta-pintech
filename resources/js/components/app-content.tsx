import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';

type Props = React.ComponentProps<'main'>;

export function AppContent({ children, className, ...props }: Props) {
    // min-w-0: sin él, el hijo flex se ensancha con su contenido y el desplazamiento interno de las tablas no se activa.
    return (
        <SidebarInset className={cn('min-w-0', className)} {...props}>
            {children}
        </SidebarInset>
    );
}
