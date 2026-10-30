import { AlertToastNotifier } from '@/components/alert-toast-notifier';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { FlashMessages } from '@/components/flash-messages';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell>
            <AppSidebar />
            <AppContent>
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="px-4 py-2">
                    <FlashMessages />
                </div>
                {/* Caja de bloque para la página: dentro del flex de <main>, una raíz con mx-auto toma el ancho de su
                    contenido (una tabla, el nombre largo de un cliente en un selector) y no el de la pantalla, y en el
                    celular la página entera se ensancha. */}
                <div className="min-w-0">{children}</div>
            </AppContent>
            <AlertToastNotifier />
        </AppShell>
    );
}
