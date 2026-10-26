import * as React from 'react';
import { SidebarInset } from '@/components/ui/sidebar';

type Props = React.ComponentProps<'main'>;

export function AppContent({ children, ...props }: Props) {
    return <SidebarInset {...props}>{children}</SidebarInset>;
}
